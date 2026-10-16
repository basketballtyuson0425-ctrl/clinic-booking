<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 予約完了メール（テキストのみ。どのメールアプリでも同じように読めるよう HTML メールにはしない）
 */
class ReservationReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $manageUrl  予約管理ページの URL（鍵入り）
     */
    public function __construct(
        public Reservation $reservation,
        public string $manageUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.config('clinic.name').'】ご予約を承りました',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.reservation-received');
    }
}
