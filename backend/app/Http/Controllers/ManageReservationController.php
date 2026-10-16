<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateManagedReservationRequest;
use App\Models\Reservation;
use App\Services\Availability;
use App\Services\ReservationBooking;
use App\Services\ReservationMailer;
use Illuminate\Http\JsonResponse;

/**
 * 予約管理ページ（/reservation/{鍵}）の API。ログインの代わりに、推測できない鍵で本人を確かめる。
 */
class ManageReservationController extends Controller
{
    /**
     * GET /api/manage/{token}
     */
    public function show(string $token): JsonResponse
    {
        return $this->respond($this->findOrFail($token));
    }

    /**
     * PATCH /api/manage/{token}
     * 予約日時・施術希望内容を変更し、変更のお知らせメールを送る
     */
    public function update(UpdateManagedReservationRequest $request, string $token, ReservationBooking $booking, ReservationMailer $mailer): JsonResponse
    {
        $reservation = $this->findOrFail($token);

        $changed = $booking->changeForPatient($reservation, $request->validated());

        // 変更が終わってから送る。送信に失敗しても変更は成立させる
        $mailSent = $changed && $mailer->sendChanged($reservation, $token);

        return $this->respond($reservation, ['changed' => $changed, 'mail_sent' => $mailSent]);
    }

    /**
     * POST /api/manage/{token}/cancel
     * キャンセルし、キャンセルのお知らせメールを送る
     */
    public function cancel(string $token, ReservationBooking $booking, ReservationMailer $mailer): JsonResponse
    {
        $reservation = $this->findOrFail($token);

        $booking->cancelForPatient($reservation);

        $mailSent = $mailer->sendCancelled($reservation);

        return $this->respond($reservation, ['mail_sent' => $mailSent]);
    }

    /**
     * 鍵に合う、終了時刻を過ぎていない予約を探す。なければ 404
     */
    private function findOrFail(string $token): Reservation
    {
        $reservation = Reservation::findByManageToken($token);

        if ($reservation === null) {
            abort(404, 'この予約管理ページは無効か、有効期限が切れています。');
        }

        return $reservation;
    }

    /**
     * 予約管理ページに表示する内容。電話番号とメールアドレスは一部を伏せる（URL が他人に見られた場合に備える）
     *
     * @param  array<string, mixed>  $extra
     */
    private function respond(Reservation $reservation, array $extra = []): JsonResponse
    {
        $reason = $reservation->patientCannotModifyReason();

        return response()
            ->json([
                'reservation' => [
                    'start_at' => $reservation->start_at->format('Y-m-d H:i'),
                    'end_at' => $reservation->end_at->format('Y-m-d H:i'),
                    'status' => $reservation->status->value,
                    'patient_name' => $reservation->patient_name,
                    'patient_phone_masked' => $reservation->maskedPhone(),
                    'patient_email_masked' => $reservation->maskedEmail(),
                    'visit_type' => $reservation->visit_type->value,
                    'treatment_request' => $reservation->treatment_request,
                    'has_email' => $reservation->patient_email !== null,
                    'can_modify' => $reason === null,
                    'cannot_modify_reason' => $reason,
                    // オンラインで変更・キャンセルできる期限（予約時刻の60分前）
                    'modifiable_until' => $reservation->start_at->subMinutes(Availability::DEADLINE_MINUTES)->format('Y-m-d H:i'),
                ],
                ...$extra,
            ])
            // 個人情報を含むので、ブラウザや途中のサーバーに保存させない
            ->header('Cache-Control', 'no-store, private');
    }
}
