<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Services\ReservationBooking;
use App\Services\ReservationMailer;
use Illuminate\Http\JsonResponse;

/**
 * 患者のネット予約（ログイン不要）
 */
class ReservationController extends Controller
{
    /**
     * POST /api/reservations
     * 入力チェック（StoreReservationRequest）を通った内容で予約を確定し、予約完了画面に必要な情報を返す。
     */
    public function store(StoreReservationRequest $request, ReservationBooking $booking, ReservationMailer $mailer): JsonResponse
    {
        [$reservation, $token] = $booking->createForPatient($request->validated());

        // 予約の保存が終わってから送る。送信に失敗しても予約は成立させる
        $mailSent = $mailer->sendReceived($reservation, $token);

        return response()->json([
            'start_at' => $reservation->start_at->format('Y-m-d H:i'),
            'patient_name' => $reservation->patient_name,
            'has_email' => $reservation->patient_email !== null,
            'mail_sent' => $mailSent,
            // 予約管理 URL（/reservation/{鍵}）を作るための鍵。DB にはハッシュ値しかないので、渡せるのはこのときだけ
            'manage_token' => $token,
        ], 201);
    }
}
