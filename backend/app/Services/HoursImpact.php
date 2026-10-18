<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\BusinessDayOverride;
use App\Models\Reservation;
use App\Models\WeeklySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 営業時間の変更が、予約にどう影響するかを調べる。
 *
 * 「影響のある予約」＝予約中で、開始時刻が今より後で、営業時間の30分枠に入らない予約。
 * 予約を自動でキャンセル・変更はしない。院長が患者に連絡するための材料を出すだけ（要件25）
 */
class HoursImpact
{
    public function __construct(private BusinessHours $businessHours) {}

    /**
     * 日付ごとの設定を変えたら、その日の営業時間と予約がどうなるか。
     *
     * @param  BusinessDayOverride|null  $override  保存前の新しい個別設定。null なら「通常設定に戻す」
     * @return array{schedule: DaySchedule, reservations: Collection<int, Reservation>, affected: Collection<int, Reservation>}
     */
    public function forDayChange(string $date, ?BusinessDayOverride $override): array
    {
        $day = CarbonImmutable::parse($date);
        $weekly = WeeklySchedule::where('day_of_week', $day->dayOfWeek)->first();
        $schedule = $this->businessHours->resolveDay($day, $override, $weekly);

        $reservations = $this->upcoming()->where('active_date', $date)->get();

        return [
            'schedule' => $schedule,
            'reservations' => $reservations,
            'affected' => $reservations->reject(fn (Reservation $reservation) => $this->fits($reservation, $schedule))->values(),
        ];
    }

    /**
     * 曜日ごとの基本設定を変えたら、これから先の予約のうち、どれが営業時間から外れるか。
     * 日付ごとの個別設定がある日・祝日は、曜日の設定を変えても営業時間が変わらないので、そのまま判定される
     *
     * @param  Collection<int, WeeklySchedule>  $weeklySchedules  保存前の新しい設定（キーは曜日 0〜6）
     * @return list<array{reservation: Reservation, schedule: DaySchedule}>
     */
    public function forWeeklyChange(Collection $weeklySchedules): array
    {
        $reservations = $this->upcoming()->get();
        $overrides = BusinessDayOverride::whereIn('date', $reservations->pluck('active_date')->unique())->get()->keyBy('date');

        $affected = [];
        foreach ($reservations as $reservation) {
            $day = CarbonImmutable::parse($reservation->active_date);
            $schedule = $this->businessHours->resolveDay($day, $overrides->get($day->toDateString()), $weeklySchedules->get($day->dayOfWeek));

            if (! $this->fits($reservation, $schedule)) {
                $affected[] = ['reservation' => $reservation, 'schedule' => $schedule];
            }
        }

        return $affected;
    }

    /**
     * 「要連絡」の予約の ID（今の営業時間から外れている、これから先の予約中の予約）。
     * DB には持たず、その場で判定する。予約の日時を変えるかキャンセルすると、要連絡ではなくなる
     *
     * @param  Collection<int, Reservation>|null  $reservations  調べる予約（省略すると、これから先のすべての予約）
     * @return list<int>
     */
    public function needsContactIds(?Collection $reservations = null): array
    {
        $targets = ($reservations ?? $this->upcoming()->get())
            ->filter(fn (Reservation $reservation) => $reservation->status === ReservationStatus::Reserved && $reservation->start_at->isFuture());

        if ($targets->isEmpty()) {
            return [];
        }

        // 予約がある期間の営業時間を、まとめて1回で計算する
        $schedules = $this->businessHours->forDates($targets->min('start_at'), $targets->max('start_at'));

        return $targets
            ->reject(fn (Reservation $reservation) => $this->fits($reservation, $schedules[$reservation->start_at->toDateString()]))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * 予約中で、開始時刻が今より後の予約（日時の早い順）
     */
    private function upcoming(): Builder
    {
        return Reservation::query()
            ->where('status', ReservationStatus::Reserved)
            ->where('start_at', '>', now())
            ->orderBy('start_at');
    }

    /**
     * 予約の時刻が、その日の営業時間の30分枠に入っているか
     */
    private function fits(Reservation $reservation, DaySchedule $schedule): bool
    {
        return $schedule->hasSlot($reservation->start_at->format('H:i'));
    }
}
