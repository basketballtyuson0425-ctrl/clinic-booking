<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonInterface;

/**
 * 管理画面の営業日カレンダーに出す、日ごとの内容（営業時間・祝日名・予約の件数・要連絡の件数）
 */
class BusinessCalendar
{
    public function __construct(
        private BusinessHours $businessHours,
        private HoursImpact $impact,
    ) {}

    /**
     * @return list<array{date: string, is_closed: bool, am: array{start: string, end: string}|null, pm: array{start: string, end: string}|null, source: string, holiday_name: string|null, has_override: bool, reservation_count: int, needs_contact_count: int}>
     */
    public function days(CarbonInterface $from, CarbonInterface $to): array
    {
        $schedules = $this->businessHours->forDates($from, $to);

        // 期間内の予約中の予約を、まとめて1回で取得する（active_date はキャンセル済みなら NULL）
        $reservations = Reservation::query()
            ->whereBetween('active_date', [$from->toDateString(), $to->toDateString()])
            ->get();
        $needsContactIds = $this->impact->needsContactIds($reservations);

        $days = [];
        foreach ($schedules as $date => $schedule) {
            $ofDay = $reservations->where('active_date', $date);
            $days[] = [
                'date' => $date,
                ...$schedule->toArray(),
                'source' => $schedule->source,
                'holiday_name' => $schedule->holidayName,
                'has_override' => $schedule->source === DaySchedule::SOURCE_OVERRIDE,
                'reservation_count' => $ofDay->count(),
                'needs_contact_count' => $ofDay->whereIn('id', $needsContactIds)->count(),
            ];
        }

        return $days;
    }
}
