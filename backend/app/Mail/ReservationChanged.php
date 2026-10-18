<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 予約変更のお知らせ（テキストのみ）
 */
class ReservationChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $manageUrl  予約管理ページの URL（鍵入り）。院長が変更したときは null。
     *                                  DB には鍵のハッシュ値しかなく、元の URL を作れないため
     */
    public function __construct(
        public Reservation $reservation,
        public ?string $manageUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.config('clinic.name').'】'.($this->manageUrl === null ? 'ご予約日時の変更のお知らせ' : 'ご予約の変更を承りました'),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.reservation-changed');
    }
}
