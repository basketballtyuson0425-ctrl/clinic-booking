<?php

namespace App\Mail;

use App\Enums\VisitType;
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
        $reservation = $this->reservation;

        return new Content(
            text: 'mail.reservation-received',
            with: [
                // 例：2026年11月6日（金）10:00〜10:30
                'dateTime' => $reservation->start_at->locale('ja')->isoFormat('YYYY年M月D日（ddd）H:mm')
                    .'〜'.$reservation->end_at->format('H:i'),
                'visitType' => $reservation->visit_type === VisitType::First ? '初めて' : '2回目以降',
                'treatmentRequest' => $reservation->treatment_request ?? '（なし）',
                'clinicName' => config('clinic.name'),
                'clinicPhone' => config('clinic.phone'),
            ],
        );
    }
}
