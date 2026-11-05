<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 曜日ごとの基本の営業時間（weekly_schedules テーブル）
 *
 * 時刻の列（am_start など）は "09:00:00" の形の文字列で扱う。
 */
#[Fillable(['day_of_week', 'is_closed', 'am_start', 'am_end', 'pm_start', 'pm_end', 'closed_weeks_of_month'])]
class WeeklySchedule extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * 休診にする「第N週」を数の配列で返す。例："1,3" → [1, 3]、未設定 → []
     *
     * @return list<int>
     */
    public function closedWeeks(): array
    {
        if ($this->closed_weeks_of_month === null || $this->closed_weeks_of_month === '') {
            return [];
        }

        return array_map('intval', explode(',', $this->closed_weeks_of_month));
    }

    /**
     * 画面に返す形（時刻は "09:00"、休診にする第N週は [1, 3]）。
     * 管理画面の設定と、患者側の受付時間の表で同じ形を使う
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        $time = fn (?string $value) => $value === null ? null : substr($value, 0, 5);

        return [
            'day_of_week' => $this->day_of_week,
            'is_closed' => $this->is_closed,
            'am_start' => $time($this->am_start),
            'am_end' => $time($this->am_end),
            'pm_start' => $time($this->pm_start),
            'pm_end' => $time($this->pm_end),
            'closed_weeks' => $this->closedWeeks(),
        ];
    }
}
