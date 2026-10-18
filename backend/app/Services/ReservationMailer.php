<?php

namespace App\Services;

use App\Enums\MailStatus;
use App\Enums\MailType;
use App\Mail\ReservationCancelled;
use App\Mail\ReservationChanged;
use App\Mail\ReservationReceived;
use App\Models\MailLog;
use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * 患者へのメールを送り、結果を mail_logs に記録する。
 *
 * メールの送信に失敗しても予約は取り消さない。院長が管理画面で失敗を確認できるよう、結果を必ず記録する。
 * キューは使わず、その場で送る（常駐プロセスが不要なので、レンタルサーバーでも動く）。
 */
class ReservationMailer
{
    /**
     * 予約完了メールを送る。メールアドレスがなければ何もしない
     *
     * @param  string  $token  予約管理 URL の鍵（DB にはハッシュ値しかないので、予約を作った直後に受け取る）
     * @return bool 送信できたか
     */
    public function sendReceived(Reservation $reservation, string $token): bool
    {
        if ($reservation->patient_email === null) {
            return false;
        }

        return $this->send($reservation, MailType::Reserved, new ReservationReceived($reservation, self::manageUrl($token)));
    }

    /**
     * 予約変更のお知らせを送る。メールアドレスがなければ何もしない
     *
     * @param  string|null  $token  予約管理 URL の鍵。患者が予約管理ページから変更したときは、リクエストの URL に含まれている。
     *                              院長が変更したときは null（DB にはハッシュ値しかないので、URL をメールに載せない）
     */
    public function sendChanged(Reservation $reservation, ?string $token): bool
    {
        if ($reservation->patient_email === null) {
            return false;
        }

        $manageUrl = $token === null ? null : self::manageUrl($token);

        return $this->send($reservation, MailType::Changed, new ReservationChanged($reservation, $manageUrl));
    }

    /**
     * 院長が「予約内容のお知らせ」を再送する。メールアドレスがなければ何もしない
     *
     * @param  string  $token  作り直した予約管理 URL の鍵
     */
    public function sendResent(Reservation $reservation, string $token): bool
    {
        if ($reservation->patient_email === null) {
            return false;
        }

        return $this->send($reservation, MailType::Resent, new ReservationReceived($reservation, self::manageUrl($token), resent: true));
    }

    /**
     * 予約キャンセルのお知らせを送る。メールアドレスがなければ何もしない
     */
    public function sendCancelled(Reservation $reservation): bool
    {
        if ($reservation->patient_email === null) {
            return false;
        }

        return $this->send($reservation, MailType::Cancelled, new ReservationCancelled($reservation));
    }

    /**
     * 予約管理ページの URL（例：https://example.com/reservation/1a2b…）
     */
    public static function manageUrl(string $token): string
    {
        return config('clinic.frontend_url').'/reservation/'.$token;
    }

    private function send(Reservation $reservation, MailType $type, Mailable $mail): bool
    {
        try {
            Mail::to($reservation->patient_email)->send($mail);
        } catch (Throwable $e) {
            MailLog::create([
                'reservation_id' => $reservation->id,
                'type' => $type,
                'to_email' => $reservation->patient_email,
                'status' => MailStatus::Failed,
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
            // エラーログには個人情報（メールアドレス・氏名）を書かず、予約番号だけを残す
            Log::warning('メールの送信に失敗しました', ['reservation_id' => $reservation->id, 'type' => $type->value]);

            return false;
        }

        MailLog::create([
            'reservation_id' => $reservation->id,
            'type' => $type,
            'to_email' => $reservation->patient_email,
            'status' => MailStatus::Sent,
            'sent_at' => now(),
        ]);

        return true;
    }
}
