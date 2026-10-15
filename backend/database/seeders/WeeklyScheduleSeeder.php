<?php

namespace Database\Seeders;

use App\Models\WeeklySchedule;
use Illuminate\Database\Seeder;

/**
 * 曜日ごとの基本の営業時間を登録する。
 * アプリの動作に必要なデータなので、本番環境でも実行する。
 * 何度実行しても行は増えない（曜日ごとに「あれば更新・なければ作成」）。
 */
class WeeklyScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $weekday = [
            'is_closed' => false,
            'am_start' => '09:00:00',
            'am_end' => '13:00:00',
            'pm_start' => '16:00:00',
            'pm_end' => '19:00:00',
            'closed_weeks_of_month' => null,
        ];

        $schedules = [
            0 => ['is_closed' => true, 'am_start' => null, 'am_end' => null, 'pm_start' => null, 'pm_end' => null, 'closed_weeks_of_month' => null], // 日曜：休診
            1 => $weekday, // 月曜
            2 => $weekday, // 火曜
            3 => [...$weekday, 'closed_weeks_of_month' => '1,3'], // 水曜：第1・第3水曜は休診
            4 => $weekday, // 木曜
            5 => $weekday, // 金曜
            6 => [...$weekday, 'pm_start' => null, 'pm_end' => null], // 土曜：午前のみ
        ];

        foreach ($schedules as $dayOfWeek => $values) {
            WeeklySchedule::updateOrCreate(['day_of_week' => $dayOfWeek], $values);
        }
    }
}
