<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;

/**
 * ある1日の営業時間（BusinessHours が決めた結果）
 *
 * 前半枠・後半枠は ['start' => '09:00', 'end' => '13:00'] の形。休みの枠は null。
 */
class DaySchedule
{
    public readonly bool $isClosed;

    public function __construct(
        public readonly string $date,
        public readonly ?array $am,
        public readonly ?array $pm,
    ) {
        // 前半・後半のどちらも休みなら、その日は休診
        $this->isClosed = $am === null && $pm === null;
    }

    public static function closed(string $date): self
    {
        return new self($date, null, null);
    }

    /**
     * 30分ごとの予約枠の開始時刻を返す。
     * 例：9:00〜13:00 → ['09:00', '09:30', ..., '12:30']（13:00 は含めない）
     *
     * @return array{am: list<string>|null, pm: list<string>|null}
     */
    public function slotTimes(): array
    {
        return [
            'am' => $this->am === null ? null : self::timesBetween($this->am['start'], $this->am['end']),
            'pm' => $this->pm === null ? null : self::timesBetween($this->pm['start'], $this->pm['end']),
        ];
    }

    /**
     * @return list<string>
     */
    private static function timesBetween(string $start, string $end): array
    {
        $time = CarbonImmutable::createFromFormat('H:i', $start);
        $endTime = CarbonImmutable::createFromFormat('H:i', $end);
        $times = [];

        // 枠の終わり（開始＋30分）が営業終了を超えない間だけ枠を作る
        while ($time->addMinutes(Reservation::SLOT_MINUTES)->lte($endTime)) {
            $times[] = $time->format('H:i');
            $time = $time->addMinutes(Reservation::SLOT_MINUTES);
        }

        return $times;
    }
}
