<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 営業日カレンダー（1か月分の営業時間・予約の件数・要連絡の件数）
 */
class CalendarController extends Controller
{
    /**
     * GET /api/admin/calendar?month=2026-11
     */
    public function show(Request $request, BusinessCalendar $calendar): JsonResponse
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $first = CarbonImmutable::createFromFormat('!Y-m', $request->query('month'));

        return response()->json([
            'month' => $first->format('Y-m'),
            'days' => $calendar->days($first, $first->endOfMonth()),
        ]);
    }
}
