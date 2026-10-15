<?php

namespace Tests\Feature;

use App\Models\BusinessDayOverride;
use App\Services\BusinessHours;
use App\Services\DaySchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 営業時間の決定（個別設定 ＞ 祝日 ＞ 曜日ごとの基本設定）を確かめる
 */
class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
    }

    private function scheduleFor(string $date): DaySchedule
    {
        return app(BusinessHours::class)->forDate(CarbonImmutable::parse($date));
    }

    public function test_平日は前半8枠と後半6枠になる(): void
    {
        $schedule = $this->scheduleFor('2026-11-02'); // 月曜

        $this->assertFalse($schedule->isClosed);
        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30'],
            $schedule->slotTimes()['am'],
        );
        $this->assertSame(
            ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30'],
            $schedule->slotTimes()['pm'],
        );
    }

    public function test_日曜は休診(): void
    {
        $schedule = $this->scheduleFor('2026-11-01'); // 日曜

        $this->assertTrue($schedule->isClosed);
        $this->assertSame(['am' => null, 'pm' => null], $schedule->slotTimes());
    }

    public function test_土曜は午前のみ(): void
    {
        $schedule = $this->scheduleFor('2026-11-07'); // 土曜

        $this->assertFalse($schedule->isClosed);
        $this->assertCount(8, $schedule->slotTimes()['am']);
        $this->assertNull($schedule->slotTimes()['pm']);
    }

    public function test_第1と第3水曜は休診(): void
    {
        $this->assertTrue($this->scheduleFor('2026-11-04')->isClosed); // 第1水曜
        $this->assertTrue($this->scheduleFor('2026-11-18')->isClosed); // 第3水曜
    }

    public function test_第2と第4と第5水曜は営業(): void
    {
        $this->assertFalse($this->scheduleFor('2026-11-11')->isClosed); // 第2水曜
        $this->assertFalse($this->scheduleFor('2026-11-25')->isClosed); // 第4水曜
        $this->assertFalse($this->scheduleFor('2026-09-30')->isClosed); // 第5水曜
    }

    public function test_祝日は休診(): void
    {
        $this->assertTrue($this->scheduleFor('2026-11-03')->isClosed); // 文化の日（火曜）
    }

    public function test_振替休日と国民の休日も休診(): void
    {
        $this->assertTrue($this->scheduleFor('2027-03-22')->isClosed); // 振替休日（月曜）
        $this->assertTrue($this->scheduleFor('2026-09-22')->isClosed); // 国民の休日（火曜）
    }

    public function test_個別設定があれば祝日でも営業できる(): void
    {
        BusinessDayOverride::create([
            'date' => '2026-11-03',
            'is_closed' => false,
            'am_start' => '09:00:00',
            'am_end' => '13:00:00',
        ]);

        $schedule = $this->scheduleFor('2026-11-03');

        $this->assertFalse($schedule->isClosed);
        $this->assertCount(8, $schedule->slotTimes()['am']);
        $this->assertNull($schedule->slotTimes()['pm']);
    }

    public function test_個別設定で平日を休診にできる(): void
    {
        BusinessDayOverride::create(['date' => '2026-11-02', 'is_closed' => true]);

        $this->assertTrue($this->scheduleFor('2026-11-02')->isClosed);
    }

    public function test_個別設定で休診の水曜を営業にできる(): void
    {
        BusinessDayOverride::create([
            'date' => '2026-11-04', // 第1水曜
            'is_closed' => false,
            'pm_start' => '16:00:00',
            'pm_end' => '19:00:00',
        ]);

        $schedule = $this->scheduleFor('2026-11-04');

        $this->assertNull($schedule->slotTimes()['am']);
        $this->assertCount(6, $schedule->slotTimes()['pm']);
    }

    public function test_個別設定で営業時間を変えられる(): void
    {
        BusinessDayOverride::create([
            'date' => '2026-11-02',
            'is_closed' => false,
            'am_start' => '10:00:00',
            'am_end' => '13:00:00',
            'pm_start' => '16:00:00',
            'pm_end' => '18:00:00',
        ]);

        $schedule = $this->scheduleFor('2026-11-02');

        $this->assertSame(['10:00', '10:30', '11:00', '11:30', '12:00', '12:30'], $schedule->slotTimes()['am']);
        $this->assertSame(['16:00', '16:30', '17:00', '17:30'], $schedule->slotTimes()['pm']);
    }

    public function test_終了時刻からはみ出す枠は作らない(): void
    {
        BusinessDayOverride::create([
            'date' => '2026-11-02',
            'is_closed' => false,
            'am_start' => '10:00:00',
            'am_end' => '12:45:00',
        ]);

        // 12:30 開始だと 13:00 に終わり 12:45 を超えるので含めない
        $this->assertSame(
            ['10:00', '10:30', '11:00', '11:30', '12:00'],
            $this->scheduleFor('2026-11-02')->slotTimes()['am'],
        );
    }

    public function test_個別設定で前半も後半も空なら休診として扱う(): void
    {
        BusinessDayOverride::create(['date' => '2026-11-02', 'is_closed' => false]);

        $this->assertTrue($this->scheduleFor('2026-11-02')->isClosed);
    }

    public function test_期間を指定するとその日数分の結果を返す(): void
    {
        $schedules = app(BusinessHours::class)->forDates(
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-30'),
        );

        $this->assertCount(30, $schedules);
        $this->assertSame('2026-11-01', array_key_first($schedules));
        $this->assertSame('2026-11-30', array_key_last($schedules));
    }
}
