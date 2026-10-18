<?php

namespace App\Mail;

use App\Enums\CancelledBy;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 予約キャンセルのお知らせ（テキストのみ）。
 * 患者がキャンセルしたときは「承りました」、院長がキャンセルしたときは「キャンセルいたしました」にする
 */
class ReservationCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.config('clinic.name').'】'.($this->byAdmin() ? 'ご予約をキャンセルいたしました' : 'ご予約のキャンセルを承りました'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.reservation-cancelled',
            with: [
                'byAdmin' => $this->byAdmin(),
                'reserveUrl' => config('clinic.frontend_url').'/reserve',
            ],
        );
    }

    private function byAdmin(): bool
    {
        return $this->reservation->cancelled_by === CancelledBy::Admin;
    }
}
