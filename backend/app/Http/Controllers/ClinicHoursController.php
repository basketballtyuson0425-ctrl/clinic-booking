<?php

namespace App\Http\Controllers;

use App\Models\WeeklySchedule;
use Illuminate\Http\JsonResponse;

/**
 * 院の受付時間（ログイン不要）。患者側の画面の下に出す「受付時間の表」に使う
 *
 * 院長が管理画面で曜日ごとの営業時間を変えると、表も自動で変わる。
 * 日付ごとの臨時の変更は含めない（予約の日付の画面に、その日の実際の空き状況が出るため）
 */
class ClinicHoursController extends Controller
{
    /**
     * GET /api/clinic/hours
     */
    public function show(): JsonResponse
    {
        $schedules = WeeklySchedule::orderBy('day_of_week')->get()
            ->map(fn (WeeklySchedule $schedule) => $schedule->toDisplayArray())
            ->all();

        return response()->json(['schedules' => $schedules]);
    }
}
