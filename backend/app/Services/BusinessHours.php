<?php

namespace App\Services;

use App\Models\BusinessDayOverride;
use App\Models\WeeklySchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Yasumi;

/**
 * 日付ごとの営業時間を決める。
 *
 * 優先順位：① 日付ごとの個別設定 ＞ ② 祝日（休診）＞ ③ 曜日ごとの基本設定（第N週のルールを含む）
 */
class BusinessHours
{
    /** @var array<int, AbstractProvider> 年ごとの祝日一覧（同じ年を何度も計算しないよう覚えておく） */
    private array $holidaysByYear = [];

    public function forDate(CarbonInterface $date): DaySchedule
    {
        return $this->forDates($date, $date)[$date->toDateString()];
    }

    /**
     * 期間内の各日の営業時間を返す。DB への問い合わせは期間全体でまとめて行う。
     *
     * @return array<string, DaySchedule> キーは 'Y-m-d'
     */
    public function forDates(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse($to->toDateString());

        $overrides = BusinessDayOverride::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy('date');
        $weeklySchedules = WeeklySchedule::all()->keyBy('day_of_week');

        $result = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $result[$date] = $this->resolve(
                CarbonImmutable::parse($date),
                $overrides->get($date),
                $weeklySchedules->get($day->dayOfWeek),
            );
        }

        return $result;
    }

    private function resolve(CarbonImmutable $day, ?BusinessDayOverride $override, ?WeeklySchedule $weekly): DaySchedule
    {
        $date = $day->toDateString();

        // ① 日付ごとの個別設定があれば、祝日や曜日に関係なくそれに従う
        if ($override !== null) {
            return $override->is_closed
                ? DaySchedule::closed($date)
                : new DaySchedule($date, self::period($override->am_start, $override->am_end), self::period($override->pm_start, $override->pm_end));
        }

        // ② 祝日は休診
        if ($this->isHoliday($day)) {
            return DaySchedule::closed($date);
        }

        // ③ 曜日ごとの基本設定。設定が見つからない曜日は、誤って予約を受けないよう休診にする
        if ($weekly === null || $weekly->is_closed) {
            return DaySchedule::closed($date);
        }

        // 1〜7日が第1週、8〜14日が第2週…（例：第1水曜は必ず1〜7日のどれか）
        $weekOfMonth = intdiv($day->day - 1, 7) + 1;
        if (in_array($weekOfMonth, $weekly->closedWeeks(), true)) {
            return DaySchedule::closed($date);
        }

        return new DaySchedule($date, self::period($weekly->am_start, $weekly->am_end), self::period($weekly->pm_start, $weekly->pm_end));
    }

    private function isHoliday(CarbonImmutable $day): bool
    {
        $this->holidaysByYear[$day->year] ??= Yasumi::create('Japan', $day->year);

        return $this->holidaysByYear[$day->year]->isHoliday($day);
    }

    /**
     * DB の時刻（"09:00:00"）を "09:00" の形にそろえる。開始・終了のどちらかが空なら、その枠は休み（null）
     *
     * @return array{start: string, end: string}|null
     */
    private static function period(?string $start, ?string $end): ?array
    {
        if ($start === null || $end === null) {
            return null;
        }

        return ['start' => substr($start, 0, 5), 'end' => substr($end, 0, 5)];
    }
}
