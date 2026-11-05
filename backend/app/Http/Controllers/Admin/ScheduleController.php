<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ReservationResource;
use App\Models\Reservation;
use App\Services\BusinessHours;
use App\Services\HoursImpact;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 週のカレンダー・ダッシュボード用の予定表（日ごとの営業時間と、その日の予約中の予約）
 */
class ScheduleController extends Controller
{
    /** 一度に返す日数の上限（ダッシュボードの「今週と来週」の14日分） */
    private const MAX_DAYS = 14;

    /**
     * GET /api/admin/schedule?from=2026-11-02&days=7
     * 予約一覧と違って50件ずつに分けない（週の表の途中で予約が欠けないようにするため）。
     * キャンセル済みの予約は含めない（その枠は空き枠なので、予定表では空いているように見せる）
     */
    public function show(Request $request, BusinessHours $businessHours, HoursImpact $impact): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_DAYS],
        ]);

        $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['from']);
        $to = $from->addDays((int) ($validated['days'] ?? 7) - 1);

        // 期間内の予約中の予約を、まとめて1回で取得する（active_date はキャンセル済みなら NULL）
        $reservations = Reservation::query()
            ->with('latestMailLog')
            ->whereBetween('active_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('start_at')
            ->get();

        // 要連絡かどうかも、まとめて判定する（1件ずつ調べると、DB への問い合わせが増えるため）
        $needsContactIds = $impact->needsContactIds($reservations);
        foreach ($reservations as $reservation) {
            $reservation->needsContact = in_array($reservation->id, $needsContactIds, true);
        }

        $days = [];
        foreach ($businessHours->forDates($from, $to) as $date => $schedule) {
            $days[] = [
                'date' => $date,
                ...$schedule->toArray(),
                'holiday_name' => $schedule->holidayName,
                'reservations' => ReservationResource::collection($reservations->where('active_date', $date)->values()),
            ];
        }

        return response()->json(['days' => $days]);
    }
}
