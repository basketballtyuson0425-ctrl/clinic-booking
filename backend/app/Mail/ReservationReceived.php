<?php

namespace App\Mail;

use App\Enums\ReservationSource;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 予約完了メール（テキストのみ。どのメールアプリでも同じように読めるよう HTML メールにはしない）。
 * 院長が再送するときの「予約内容のお知らせ」にも使う
 */
class ReservationReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $manageUrl  予約管理ページの URL（鍵入り）
     * @param  bool  $resent  院長による再送か（鍵を作り直したので、以前の URL は使えなくなっている）
     */
    public function __construct(
        public Reservation $reservation,
        public string $manageUrl,
        public bool $resent = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.config('clinic.name').'】'.($this->resent ? 'ご予約内容のお知らせ' : 'ご予約を承りました'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.reservation-received',
            // 院長が電話などで受けた予約には「ネット予約を承りました」と書かない
            with: ['fromWeb' => $this->reservation->source === ReservationSource::Web],
        );
    }
}
