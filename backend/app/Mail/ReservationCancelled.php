<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 予約キャンセルのお知らせ（テキストのみ）
 */
class ReservationCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.config('clinic.name').'】ご予約のキャンセルを承りました',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.reservation-cancelled',
            with: ['reserveUrl' => config('clinic.frontend_url').'/reserve'],
        );
    }
}
