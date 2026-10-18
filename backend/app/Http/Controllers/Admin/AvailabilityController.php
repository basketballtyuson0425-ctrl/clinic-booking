<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * 院長が予約の日時を選ぶときの空き枠 API。
 * 患者用（/api/availability）と違い、30日以内・1時間前までの制限はかけない
 */
class AvailabilityController extends Controller
{
    /**
     * GET /api/admin/availability/{date}
     */
    public function show(string $date, Availability $availability): JsonResponse
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        // "2026-02-30" のような存在しない日付は、3月2日などに自動で繰り上がってしまうので弾く
        if ($day === false || $day->format('Y-m-d') !== $date) {
            return response()->json(['message' => '日付が正しくありません。'], 422);
        }

        if ($day->lt(CarbonImmutable::today())) {
            return response()->json(['message' => '過去の日付は選べません。'], 422);
        }

        return response()->json($availability->forAdminDate($day));
    }
}
