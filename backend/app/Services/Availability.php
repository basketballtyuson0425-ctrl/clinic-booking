<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * 予約画面（患者側・院長の管理画面）に出す ○／× を判定する。
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
     * 締め切り（予約時刻の60分前）より前か。過去の時刻は false になる
     */
    public static function isBeforeDeadline(CarbonInterface $startAt): bool
    {
        return $startAt->gte(CarbonImmutable::now()->addMinutes(self::DEADLINE_MINUTES));
    }

    /**
     * 患者が予約できる枠か（営業時間内の30分枠・予約可能期間内・締め切り前）。
     * すでに予約が入っているかどうかは、ここでは見ない（ReservationBooking で確認する）
     */
    public function isBookableSlot(CarbonInterface $startAt): bool
    {
        return $this->isSlotStart($startAt)
            && self::isBookableDate($startAt)
            && self::isBeforeDeadline($startAt);
    }

    /**
     * 院長が予約を入れられる枠か（営業時間内の30分枠・開始時刻が今より後）。
     * 「30日以内」と「1時間前まで」の制限は、院長にはかけない（確定仕様 6）
     */
    public function isAdminBookableSlot(CarbonInterface $startAt): bool
    {
        return $this->isSlotStart($startAt) && $startAt->isFuture();
    }

    /**
     * 期間内の各日について、30分ごとの枠と ○／× を返す。
     *
     * @return array<string, array{date: string, is_closed: bool, am: list<array{time: string, available: bool}>|null, pm: list<array{time: string, available: bool}>|null}>
     */
    public function forDates(CarbonInterface $from, CarbonInterface $to): array
    {
        $schedules = $this->businessHours->forDates($from, $to);
        $takenSlots = $this->takenSlots($from, $to);

        $result = [];
        foreach ($schedules as $date => $schedule) {
            $times = $schedule->slotTimes();
            $result[$date] = [
                'date' => $date,
                'is_closed' => $schedule->isClosed,
                'am' => $this->slots($date, $times['am'], $takenSlots),
                'pm' => $this->slots($date, $times['pm'], $takenSlots),
            ];
        }

        return $result;
    }

    /**
     * 院長の画面用：指定した日の30分ごとの枠と、それぞれに予約中の予約があるか。
     * 患者用の forDates() と違い、30日以内・1時間前までの制限はかけない（過去の枠は選べない）
     *
     * @return array{date: string, is_closed: bool, am: list<array{time: string, reserved: bool, available: bool}>|null, pm: list<array{time: string, reserved: bool, available: bool}>|null}
     */
    public function forAdminDate(CarbonInterface $date): array
    {
        $dateString = $date->toDateString();
        $schedule = $this->businessHours->forDate($date);
        $times = $schedule->slotTimes();
        $takenSlots = $this->takenSlots($date, $date);

        $slots = fn (?array $times) => $times === null ? null : array_map(function (string $time) use ($dateString, $takenSlots) {
            $reserved = $takenSlots->contains("{$dateString} {$time}");

            return [
                'time' => $time,
                'reserved' => $reserved,
                'available' => ! $reserved && CarbonImmutable::parse("{$dateString} {$time}")->isFuture(),
            ];
        }, $times);

        return [
            'date' => $dateString,
            'is_closed' => $schedule->isClosed,
            'am' => $slots($times['am']),
            'pm' => $slots($times['pm']),
        ];
    }

    /**
     * 営業時間内の30分枠の開始時刻か（例：10:00 は○、10:15 や営業時間外は×）
     */
    private function isSlotStart(CarbonInterface $startAt): bool
    {
        return $this->businessHours->forDate($startAt)->hasSlot($startAt->format('H:i')) && $startAt->second === 0;
    }

    /**
     * 期間内の「予約中の枠」をまとめて1回で取得する（例：'2026-11-02 10:00'）
     *
     * @return Collection<int, string>
     */
    private function takenSlots(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Reservation::query()
            ->whereNotNull('active_start_at')
            ->whereBetween('active_start_at', [
                CarbonImmutable::parse($from->toDateString())->startOfDay(),
                CarbonImmutable::parse($to->toDateString())->endOfDay(),
            ])
            ->get(['active_start_at'])
            ->map(fn (Reservation $reservation) => $reservation->active_start_at->format('Y-m-d H:i'));
    }

    /**
     * @param  list<string>|null  $times
     * @param  Collection<int, string>  $takenSlots
     * @return list<array{time: string, available: bool}>|null
     */
    private function slots(string $date, ?array $times, Collection $takenSlots): ?array
    {
        if ($times === null) {
            return null;
        }

        return array_map(function (string $time) use ($date, $takenSlots) {
            $startAt = CarbonImmutable::parse("{$date} {$time}");

            return [
                'time' => $time,
                'available' => ! $takenSlots->contains("{$date} {$time}")
                    && self::isBeforeDeadline($startAt)
                    && self::isBookableDate($startAt),
            ];
        }, $times);
    }
}
