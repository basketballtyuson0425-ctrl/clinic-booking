<?php

namespace Tests\Feature;

use App\Models\BusinessDayOverride;
use App\Models\WeeklySchedule;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 院の受付時間 API（GET /api/clinic/hours）。患者側の画面の下に出す「受付時間の表」の元になる
 */
class ClinicHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
    }

    public function test_ログインしなくても曜日ごとの基本の受付時間と休診の週を返す(): void
    {
        $response = $this->getJson('/api/clinic/hours');

        $response->assertOk()->assertJsonCount(7, 'schedules');
        $schedules = $response->json('schedules');

        // 日曜：休診
        $this->assertSame(
            ['day_of_week' => 0, 'is_closed' => true, 'am_start' => null, 'am_end' => null, 'pm_start' => null, 'pm_end' => null, 'closed_weeks' => []],
            $schedules[0],
        );
        // 月曜：午前・午後
        $this->assertSame(
            ['day_of_week' => 1, 'is_closed' => false, 'am_start' => '09:00', 'am_end' => '13:00', 'pm_start' => '16:00', 'pm_end' => '19:00', 'closed_weeks' => []],
            $schedules[1],
        );
        // 水曜：第1・第3水曜は休診
        $this->assertSame([1, 3], $schedules[3]['closed_weeks']);
        // 土曜：午前のみ
        $this->assertSame(['09:00', '13:00', null, null], [
            $schedules[6]['am_start'], $schedules[6]['am_end'], $schedules[6]['pm_start'], $schedules[6]['pm_end'],
        ]);
    }

    public function test_院長が曜日の営業時間を変えると返す内容も変わる(): void
    {
        WeeklySchedule::where('day_of_week', 5)->update(['pm_end' => '18:00:00']);

        $this->getJson('/api/clinic/hours')
            ->assertOk()
            ->assertJsonPath('schedules.5.pm_end', '18:00');
    }

    public function test_日付ごとの個別設定は含めず余計な値も出さない(): void
    {
        // 臨時の変更は、予約の日付の画面で確かめてもらう。受付時間の表は曜日ごとの基本だけ
        BusinessDayOverride::create(['date' => '2026-11-06', 'is_closed' => true]);

        $response = $this->getJson('/api/clinic/hours')->assertOk();

        $this->assertSame(['schedules'], array_keys($response->json()));
        $this->assertSame(
            ['day_of_week', 'is_closed', 'am_start', 'am_end', 'pm_start', 'pm_end', 'closed_weeks'],
            array_keys($response->json('schedules.5')),
        );
        $this->assertSame('19:00', $response->json('schedules.5.pm_end'));
    }
}
