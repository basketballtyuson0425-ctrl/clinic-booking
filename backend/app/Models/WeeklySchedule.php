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
}
