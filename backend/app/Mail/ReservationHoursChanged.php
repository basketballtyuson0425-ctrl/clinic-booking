<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Services\DaySchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 営業時間変更のお知らせ（テキストのみ）。
 * 営業時間を変えたことで、予約の時刻が営業時間から外れた患者さんに送る。予約は自動で変更・キャンセルしないので、電話での連絡をお願いする
 */
class ReservationHoursChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  DaySchedule  $schedule  予約日の、変更後の営業時間
     */
    public function __construct(
        public Reservation $reservation,
        public DaySchedule $schedule,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.config('clinic.name').'】営業時間変更のお知らせ',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.reservation-hours-changed',
            with: [
                'dateLabel' => $this->reservation->start_at->locale('ja')->isoFormat('M月D日（ddd）'),
                'amLabel' => self::periodLabel($this->schedule->am),
                'pmLabel' => self::periodLabel($this->schedule->pm),
            ],
        );
    }

    /**
     * ['start' => '09:00', 'end' => '13:00'] → "9:00〜13:00"、休みの枠 → "休診"
     *
     * @param  array{start: string, end: string}|null  $period
     */
    private static function periodLabel(?array $period): string
    {
        if ($period === null) {
            return '休診';
        }

        // 先頭の0を1つだけ取る（"09:00" → "9:00"）
        $trim = fn (string $time) => preg_replace('/^0/', '', $time);

        return $trim($period['start']).'〜'.$trim($period['end']);
    }
}
