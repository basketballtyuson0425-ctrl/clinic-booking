<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateReservationRequest;
use App\Http\Requests\Admin\ListReservationsRequest;
use App\Http\Requests\Admin\UpdateReservationRequest;
use App\Http\Resources\Admin\ReservationResource;
use App\Models\Reservation;
use App\Services\HoursImpact;
use App\Services\ReservationBooking;
use App\Services\ReservationMailer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 管理画面の予約の操作（一覧・登録・詳細・変更・キャンセル・メールの再送）
 */
class ReservationController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * GET /api/admin/reservations
     * 日時の早い順に50件ずつ。何も指定しなければ今日以降の予約を返す
     */
    public function index(ListReservationsRequest $request, HoursImpact $impact): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Reservation::query()->with('latestMailLog');

        if (isset($filters['date'])) {
            // 日付を指定したときは、過去の日付でもその日の予約を返す
            $day = CarbonImmutable::parse($filters['date']);
            $query->whereBetween('start_at', [$day->startOfDay(), $day->endOfDay()]);
        } elseif (! $request->boolean('include_past')) {
            $query->where('start_at', '>=', CarbonImmutable::today());
        }

        if (isset($filters['name'])) {
            $this->whereContains($query, 'name_key', Reservation::normalizeName($filters['name']));
        }
        if (isset($filters['phone'])) {
            $this->whereContains($query, 'phone_key', Reservation::normalizePhone($filters['phone']));
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($request->boolean('needs_contact')) {
            $query->whereIn('id', $impact->needsContactIds());
        }

        $reservations = $query->orderBy('start_at')->orderBy('id')->paginate(self::PER_PAGE);

        // 要連絡かどうかを、このページの予約についてまとめて判定する（1件ずつ調べると、DB への問い合わせが増えるため）
        $needsContactIds = $impact->needsContactIds($reservations->getCollection());
        foreach ($reservations as $reservation) {
            $reservation->needsContact = in_array($reservation->id, $needsContactIds, true);
        }

        return ReservationResource::collection($reservations);
    }

    /**
     * POST /api/admin/reservations
     * 院長が予約を登録する。「確認メールを送る」がオンで、メールアドレスがあるときだけ送る
     */
    public function store(CreateReservationRequest $request, ReservationBooking $booking, ReservationMailer $mailer): JsonResponse
    {
        [$reservation, $token] = $booking->createForAdmin(
            $request->safe()->except('send_mail'),
            $request->boolean('send_mail'),
        );

        // 予約の保存が終わってから送る。送信に失敗しても予約は成立させる
        $mailSent = $token !== null && $mailer->sendReceived($reservation, $token);

        return $this->respond($reservation, ['mail_sent' => $mailSent], 201);
    }

    /**
     * GET /api/admin/reservations/{id}
     */
    public function show(Reservation $reservation): JsonResponse
    {
        return $this->respond($reservation);
    }

    /**
     * PATCH /api/admin/reservations/{id}
     * 送られた項目だけを変更する。日時が変わり「お知らせメールを送る」がオンのときだけ、メールを送る
     */
    public function update(UpdateReservationRequest $request, Reservation $reservation, ReservationBooking $booking, ReservationMailer $mailer): JsonResponse
    {
        $changed = $booking->changeForAdmin($reservation, $request->safe()->except('send_mail'));

        // 患者情報・施術希望内容だけの修正（名前の誤字など）では、患者を驚かせないようメールを送らない
        $mailSent = $changed
            && $reservation->wasChanged('start_at')
            && $request->boolean('send_mail')
            && $mailer->sendChanged($reservation, null);

        return $this->respond($reservation, ['changed' => $changed, 'mail_sent' => $mailSent]);
    }

    /**
     * POST /api/admin/reservations/{id}/cancel
     */
    public function cancel(Request $request, Reservation $reservation, ReservationBooking $booking, ReservationMailer $mailer): JsonResponse
    {
        $request->validate(['send_mail' => ['required', 'boolean']]);

        $booking->cancelForAdmin($reservation);

        $mailSent = $request->boolean('send_mail') && $mailer->sendCancelled($reservation);

        return $this->respond($reservation, ['mail_sent' => $mailSent]);
    }

    /**
     * POST /api/admin/reservations/{id}/resend-mail
     * 予約管理 URL の鍵を作り直して「予約内容のお知らせ」を送る（DB には鍵のハッシュ値しかないので、元の URL は作れない）
     */
    public function resendMail(Reservation $reservation, ReservationBooking $booking, ReservationMailer $mailer): JsonResponse
    {
        $booking->ensureAdminCanResend($reservation);

        $token = $reservation->issueManageToken();
        $mailSent = $mailer->sendResent($reservation, $token);

        if ($mailSent) {
            // 送れたときだけ新しい鍵を保存する。ここで以前の URL は使えなくなる
            $reservation->save();
        } else {
            // 送れなかったときは鍵を変えない（患者の手元にある以前の URL を使えるままにする）
            $reservation->discardChanges();
        }

        return $this->respond($reservation, ['mail_sent' => $mailSent]);
    }

    /**
     * 予約1件の応答。詳細画面で使うので、メールの送信記録（新しい順）も付ける
     *
     * @param  array<string, mixed>  $extra
     */
    private function respond(Reservation $reservation, array $extra = [], int $status = 200): JsonResponse
    {
        $reservation->load(['latestMailLog', 'mailLogs' => fn ($query) => $query->latest('id')]);

        return (new ReservationResource($reservation))
            ->additional($extra)
            ->response()
            ->setStatusCode($status);
    }

    /**
     * 部分一致で探す。検索語に含まれる % と _ は、LIKE の特別な記号（何でも一致）ではなく、ただの文字として扱う
     */
    private function whereContains(Builder $query, string $column, string $value): void
    {
        // 「!」を「次の1文字はただの文字」という印（エスケープ文字）にする。SQLite と MySQL のどちらでも同じ書き方で動く
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);

        $query->whereRaw("{$column} LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
    }
}
