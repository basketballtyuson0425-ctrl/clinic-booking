<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\HoursChangeNeedsConfirmationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WeeklySchedulesRequest;
use App\Http\Resources\Admin\AffectedReservationResource;
use App\Models\Reservation;
use App\Models\WeeklySchedule;
use App\Services\DaySchedule;
use App\Services\HoursImpact;
use App\Services\ReservationMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * 曜日ごとの基本の営業時間。
 * 変えると、個別設定のない日（祝日を除く）すべての営業時間が変わるので、これから先の予約への影響を確認してから保存する
 */
class WeeklyScheduleController extends Controller
{
    /**
     * GET /api/admin/weekly-schedules
     */
    public function index(): JsonResponse
    {
        return response()->json(['schedules' => $this->schedules()]);
    }

    /**
     * POST /api/admin/weekly-schedules/impact
     * 保存したら、これから先の予約のうち、どれが営業時間から外れるか（保存はしない）
     */
    public function impact(WeeklySchedulesRequest $request, HoursImpact $impact): JsonResponse
    {
        return response()->json($this->impactSummary($impact->forWeeklyChange($request->toWeeklySchedules())));
    }

    /**
     * PUT /api/admin/weekly-schedules
     */
    public function update(WeeklySchedulesRequest $request, HoursImpact $impact, ReservationMailer $mailer): JsonResponse
    {
        $schedules = $request->toWeeklySchedules();
        $affected = $impact->forWeeklyChange($schedules);

        // 営業時間から外れる予約があるのに、院長が確認していなければ断る
        if ($affected !== [] && ! $request->boolean('confirmed')) {
            throw new HoursChangeNeedsConfirmationException($this->impactSummary($affected));
        }

        // 7曜日分を、全部保存するか全部保存しないかのどちらかにする（途中で失敗しても、半分だけ変わらないように）
        DB::transaction(function () use ($schedules) {
            foreach ($schedules as $dayOfWeek => $schedule) {
                WeeklySchedule::updateOrCreate(['day_of_week' => $dayOfWeek], $schedule->getAttributes());
            }
        });

        $mail = $request->boolean('send_mail') ? $mailer->sendHoursChangedToAll($affected) : ['sent' => 0, 'failed' => 0];

        return response()->json([
            'schedules' => $this->schedules(),
            'affected' => AffectedReservationResource::collection(array_column($affected, 'reservation'))->resolve(),
            'mail' => $mail,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function schedules(): array
    {
        return WeeklySchedule::orderBy('day_of_week')->get()
            ->map(fn (WeeklySchedule $schedule) => $schedule->toDisplayArray())
            ->all();
    }

    /**
     * @param  list<array{reservation: Reservation, schedule: DaySchedule}>  $affected
     * @return array<string, mixed>
     */
    private function impactSummary(array $affected): array
    {
        return [
            'reservation_count' => count($affected),
            'affected' => AffectedReservationResource::collection(array_column($affected, 'reservation'))->resolve(),
        ];
    }
}
