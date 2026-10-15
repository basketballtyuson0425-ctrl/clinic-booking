<?php

namespace App\Http\Controllers;

use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * 患者側の予約画面向けの空き枠 API（ログイン不要）
 *
 * ○／× だけを返し、誰が予約しているかなどの情報は返さない。
 */
class AvailabilityController extends Controller
{
    /**
     * GET /api/availability/dates
     * 予約できる期間（今日〜今日＋30日）の各日について、休診かどうかと空きがあるかを返す。
     */
    public function dates(Availability $availability): JsonResponse
    {
        $days = $availability->forDates(Availability::firstBookableDate(), Availability::lastBookableDate());

        $dates = [];
        foreach ($days as $day) {
            $slots = [...($day['am'] ?? []), ...($day['pm'] ?? [])];
            $dates[] = [
                'date' => $day['date'],
                'is_closed' => $day['is_closed'],
                'available' => in_array(true, array_column($slots, 'available'), true),
            ];
        }

        return response()->json(['dates' => $dates]);
    }

    /**
     * GET /api/availability/{date}
     * 指定した日の30分ごとの枠と ○／× を、前半（am）・後半（pm）に分けて返す。
     */
    public function show(string $date, Availability $availability): JsonResponse
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        // "2026-02-30" のような存在しない日付は、3月2日などに自動で繰り上がってしまうので弾く
        if ($day === false || $day->format('Y-m-d') !== $date) {
            return response()->json(['message' => '日付が正しくありません。'], 422);
        }

        if (! Availability::isBookableDate($day)) {
            return response()->json(['message' => 'この日付は予約を受け付けていません。'], 422);
        }

        return response()->json($availability->forDates($day, $day)[$date]);
    }
}
