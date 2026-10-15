<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * 患者側の予約画面に出す ○／× を判定する。
 *
 * ここでの判定は画面の案内用。二重予約の最終的な防止は、reservations テーブルの一意制約が担う。
 */
class Availability
{
    // 予約できるのは「今日」から「今日＋30日」の日付まで
    public const BOOKABLE_DAYS = 30;

    // 予約時刻の何分前まで予約を受け付けるか
    public const DEADLINE_MINUTES = 60;

    public function __construct(private BusinessHours $businessHours) {}

    public static function firstBookableDate(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }

    public static function lastBookableDate(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(self::BOOKABLE_DAYS);
    }

    public static function isBookableDate(CarbonInterface $date): bool
    {
        return $date->toDateString() >= self::firstBookableDate()->toDateString()
            && $date->toDateString() <= self::lastBookableDate()->toDateString();
    }

    /**
     * 期間内の各日について、30分ごとの枠と ○／× を返す。
     *
     * @return array<string, array{date: string, is_closed: bool, am: list<array{time: string, available: bool}>|null, pm: list<array{time: string, available: bool}>|null}>
     */
    public function forDates(CarbonInterface $from, CarbonInterface $to): array
    {
        $schedules = $this->businessHours->forDates($from, $to);

        // 期間内の「予約中の枠」をまとめて1回で取得する（例：'2026-11-02 10:00'）
        $takenSlots = Reservation::query()
            ->whereNotNull('active_start_at')
            ->whereBetween('active_start_at', [
                CarbonImmutable::parse($from->toDateString())->startOfDay(),
                CarbonImmutable::parse($to->toDateString())->endOfDay(),
            ])
            ->get(['active_start_at'])
            ->map(fn (Reservation $reservation) => $reservation->active_start_at->format('Y-m-d H:i'));

        // これより前に始まる枠は、締め切りを過ぎている（過去の枠も含む）
        $deadline = CarbonImmutable::now()->addMinutes(self::DEADLINE_MINUTES);

        $result = [];
        foreach ($schedules as $date => $schedule) {
            $times = $schedule->slotTimes();
            $result[$date] = [
                'date' => $date,
                'is_closed' => $schedule->isClosed,
                'am' => $this->slots($date, $times['am'], $takenSlots, $deadline),
                'pm' => $this->slots($date, $times['pm'], $takenSlots, $deadline),
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>|null  $times
     * @param  Collection<int, string>  $takenSlots
     * @return list<array{time: string, available: bool}>|null
     */
    private function slots(string $date, ?array $times, Collection $takenSlots, CarbonImmutable $deadline): ?array
    {
        if ($times === null) {
            return null;
        }

        return array_map(function (string $time) use ($date, $takenSlots, $deadline) {
            $startAt = CarbonImmutable::parse("{$date} {$time}");

            return [
                'time' => $time,
                'available' => ! $takenSlots->contains("{$date} {$time}")
                    && $startAt->gte($deadline)
                    && self::isBookableDate($startAt),
            ];
        }, $times);
    }
}
