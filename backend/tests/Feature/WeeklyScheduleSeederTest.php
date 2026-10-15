<?php

namespace Tests\Feature;

use App\Models\WeeklySchedule;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyScheduleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_曜日ごとの基本設定が7行登録される(): void
    {
        $this->seed(WeeklyScheduleSeeder::class);

        $this->assertSame(7, WeeklySchedule::count());

        $sunday = WeeklySchedule::where('day_of_week', 0)->first();
        $this->assertTrue($sunday->is_closed);

        $monday = WeeklySchedule::where('day_of_week', 1)->first();
        $this->assertFalse($monday->is_closed);
        $this->assertSame('09:00:00', $monday->am_start);
        $this->assertSame('19:00:00', $monday->pm_end);

        $wednesday = WeeklySchedule::where('day_of_week', 3)->first();
        $this->assertSame('1,3', $wednesday->closed_weeks_of_month);

        $saturday = WeeklySchedule::where('day_of_week', 6)->first();
        $this->assertSame('13:00:00', $saturday->am_end);
        $this->assertNull($saturday->pm_start);
    }

    public function test_2回実行しても行は増えない(): void
    {
        $this->seed(WeeklyScheduleSeeder::class);
        $this->seed(WeeklyScheduleSeeder::class);

        $this->assertSame(7, WeeklySchedule::count());
    }
}
