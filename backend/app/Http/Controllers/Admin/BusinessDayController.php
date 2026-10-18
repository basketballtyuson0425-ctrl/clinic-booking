<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\HoursChangeNeedsConfirmationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BusinessDayRequest;
use App\Http\Resources\Admin\AffectedReservationResource;
use App\Models\BusinessDayOverride;
use App\Models\Reservation;
use App\Services\BusinessCalendar;
use App\Services\DaySchedule;
use App\Services\HoursImpact;
use App\Services\ReservationMailer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 日付ごとの営業時間（個別設定）の変更。
 * 予約がある日は、院長が影響を確認してから保存する。予約は自動でキャンセル・変更しない（要件25）
 */
class BusinessDayController extends Controller
{
    /**
     * POST /api/admin/business-days/{date}/impact
     * 保存したら、その日の予約にどう影響するか（保存はしない）。{ "reset": true } なら「通常設定に戻す」場合
     */
    public function impact(BusinessDayRequest $request, string $date, HoursImpact $impact): JsonResponse
    {
        $this->ensureEditable($date);
        $override = $request->boolean('reset') ? null : $request->toOverride($date);

        return response()->json($this->impactSummary($impact->forDayChange($date, $override)));
    }

    /**
     * PUT /api/admin/business-days/{date}
     */
    public function update(BusinessDayRequest $request, string $date, HoursImpact $impact, ReservationMailer $mailer, BusinessCalendar $calendar): JsonResponse
    {
        $this->ensureEditable($date);
        $override = $request->toOverride($date);
        $result = $impact->forDayChange($date, $override);
        $this->ensureConfirmed($request, $result);

        BusinessDayOverride::updateOrCreate(['date' => $date], Arr::except($override->getAttributes(), 'date'));

        return $this->respond($request, $date, $result, $mailer, $calendar);
    }

    /**
     * DELETE /api/admin/business-days/{date}
     * 個別設定を消して、曜日ごとの基本設定（祝日なら休診）に戻す
     */
    public function destroy(Request $request, string $date, HoursImpact $impact, ReservationMailer $mailer, BusinessCalendar $calendar): JsonResponse
    {
        $request->validate([
            'confirmed' => ['sometimes', 'boolean'],
            'send_mail' => ['sometimes', 'boolean'],
        ]);
        $this->ensureEditable($date);
        $result = $impact->forDayChange($date, null);
        $this->ensureConfirmed($request, $result);

        BusinessDayOverride::where('date', $date)->delete();

        return $this->respond($request, $date, $result, $mailer, $calendar);
    }

    /**
     * 変更できる日付か（存在する日付で、今日以降）
     *
     * @throws ValidationException
     */
    private function ensureEditable(string $date): void
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        // "2026-02-30" のような存在しない日付は、3月2日などに自動で繰り上がってしまうので弾く
        if ($day === false || $day->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => '日付が正しくありません。']);
        }
        if ($day->lt(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['date' => '過去の日付の営業時間は変更できません。']);
        }
    }

    /**
     * その日に予約があるのに、院長が確認していなければ断る（画面の確認を飛ばした保存を防ぐ）
     *
     * @param  array{schedule: DaySchedule, reservations: Collection<int, Reservation>, affected: Collection<int, Reservation>}  $result
     *
     * @throws HoursChangeNeedsConfirmationException
     */
    private function ensureConfirmed(Request $request, array $result): void
    {
        if ($result['reservations']->isNotEmpty() && ! $request->boolean('confirmed')) {
            throw new HoursChangeNeedsConfirmationException($this->impactSummary($result));
        }
    }

    /**
     * 保存したあとの応答。影響のある予約には、指定があればお知らせメールを送る
     *
     * @param  array{schedule: DaySchedule, reservations: Collection<int, Reservation>, affected: Collection<int, Reservation>}  $result
     */
    private function respond(Request $request, string $date, array $result, ReservationMailer $mailer, BusinessCalendar $calendar): JsonResponse
    {
        $mail = ['sent' => 0, 'failed' => 0];
        if ($request->boolean('send_mail')) {
            $mail = $mailer->sendHoursChangedToAll(
                $result['affected']->map(fn (Reservation $reservation) => ['reservation' => $reservation, 'schedule' => $result['schedule']]),
            );
        }

        $day = CarbonImmutable::parse($date);

        return response()->json([
            'day' => $calendar->days($day, $day)[0],
            'affected' => AffectedReservationResource::collection($result['affected'])->resolve(),
            'mail' => $mail,
        ]);
    }

    /**
     * 確認の画面に出す内容
     *
     * @param  array{schedule: DaySchedule, reservations: Collection<int, Reservation>, affected: Collection<int, Reservation>}  $result
     * @return array<string, mixed>
     */
    private function impactSummary(array $result): array
    {
        return [
            'schedule' => $result['schedule']->toArray(),
            'reservation_count' => $result['reservations']->count(),
            'affected' => AffectedReservationResource::collection($result['affected'])->resolve(),
        ];
    }
}
