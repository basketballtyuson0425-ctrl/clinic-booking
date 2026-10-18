<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;

/**
 * ある1日の営業時間（BusinessHours が決めた結果）
 *
 * 前半枠・後半枠は ['start' => '09:00', 'end' => '13:00'] の形。休みの枠は null。
 * source は、どの設定で決まったか（override：日付ごとの個別設定／holiday：祝日／weekly：曜日ごとの基本設定）
 */
class DaySchedule
{
    public readonly bool $isClosed;

    public const SOURCE_OVERRIDE = 'override';

    public const SOURCE_HOLIDAY = 'holiday';

    public const SOURCE_WEEKLY = 'weekly';

    public function __construct(
        public readonly string $date,
        public readonly ?array $am,
        public readonly ?array $pm,
        public readonly string $source = self::SOURCE_WEEKLY,
        public readonly ?string $holidayName = null, // 祝日なら、その名前（例：文化の日）。個別設定で営業にした祝日にも付く
    ) {
        // 前半・後半のどちらも休みなら、その日は休診
        $this->isClosed = $am === null && $pm === null;
    }

    public static function closed(string $date, string $source = self::SOURCE_WEEKLY, ?string $holidayName = null): self
    {
        return new self($date, null, null, $source, $holidayName);
    }

    /**
     * "10:00" が、この日の30分枠の開始時刻のどれかに当たるか（予約が営業時間内に入っているかの判定）
     */
    public function hasSlot(string $time): bool
    {
        $times = $this->slotTimes();

        return in_array($time, [...($times['am'] ?? []), ...($times['pm'] ?? [])], true);
    }

    /**
     * 画面や API に返す形
     *
     * @return array{is_closed: bool, am: array{start: string, end: string}|null, pm: array{start: string, end: string}|null}
     */
    public function toArray(): array
    {
        return ['is_closed' => $this->isClosed, 'am' => $this->am, 'pm' => $this->pm];
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
