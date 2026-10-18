<?php

namespace App\Services;

use App\Models\BusinessDayOverride;
use App\Models\WeeklySchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Yasumi\Holiday;
use Yasumi\Yasumi;

/**
 * 日付ごとの営業時間を決める。
 *
 * 優先順位：① 日付ごとの個別設定 ＞ ② 祝日（休診）＞ ③ 曜日ごとの基本設定（第N週のルールを含む）
 */
class BusinessHours
{
    /** @var array<int, array<string, string>> 年ごとの「日付 → 祝日名」の一覧 */
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
            $result[$date] = $this->resolveDay(
                CarbonImmutable::parse($date),
                $overrides->get($date),
                $weeklySchedules->get($day->dayOfWeek),
            );
        }

        return $result;
    }

    /**
     * 1日の営業時間を決める。保存前の設定（新しい個別設定・新しい曜日の設定）を渡せば、
     * 「その設定にしたら営業時間がどうなるか」も同じ手順で計算できる（営業時間の変更の影響を調べるときに使う）
     */
    public function resolveDay(CarbonImmutable $day, ?BusinessDayOverride $override, ?WeeklySchedule $weekly): DaySchedule
    {
        $date = $day->toDateString();
        $holidayName = $this->holidayName($day);

        // ① 日付ごとの個別設定があれば、祝日や曜日に関係なくそれに従う
        if ($override !== null) {
            return $override->is_closed
                ? DaySchedule::closed($date, DaySchedule::SOURCE_OVERRIDE, $holidayName)
                : new DaySchedule($date, self::period($override->am_start, $override->am_end), self::period($override->pm_start, $override->pm_end), DaySchedule::SOURCE_OVERRIDE, $holidayName);
        }

        // ② 祝日は休診
        if ($holidayName !== null) {
            return DaySchedule::closed($date, DaySchedule::SOURCE_HOLIDAY, $holidayName);
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

    /**
     * 祝日なら、その日本語の名前（例：文化の日、振替休日）。祝日でなければ null
     */
    private function holidayName(CarbonImmutable $day): ?string
    {
        // 同じ年を何度も計算しないよう、年ごとに「日付 → 祝日名」の一覧を覚えておく
        $this->holidaysByYear[$day->year] ??= collect(Yasumi::create('Japan', $day->year, 'ja_JP')->getHolidays())
            ->mapWithKeys(fn (Holiday $holiday) => [$holiday->format('Y-m-d') => $holiday->getName()])
            ->all();

        return $this->holidaysByYear[$day->year][$day->toDateString()] ?? null;
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
