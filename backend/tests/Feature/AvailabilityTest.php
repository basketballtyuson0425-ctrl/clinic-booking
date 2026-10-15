<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Models\Reservation;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 患者側の ○／× の判定（予約済み・締め切り・予約可能期間）を確かめる
 */
class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        // 「今」を 2026-11-05（木）8:30 に固定する
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00'));
    }

    /**
     * 指定した日の枠を ['09:00' => true, ...] の形で返す
     *
     * @return array<string, bool>
     */
    private function availabilityOf(string $date): array
    {
        $day = app(Availability::class)->forDates(CarbonImmutable::parse($date), CarbonImmutable::parse($date))[$date];
        $slots = [...($day['am'] ?? []), ...($day['pm'] ?? [])];

        return array_column($slots, 'available', 'time');
    }

    public function test_予約中の枠は予約できない(): void
    {
        Reservation::factory()->create(['start_at' => '2026-11-06 10:00:00']);

        $slots = $this->availabilityOf('2026-11-06');

        $this->assertFalse($slots['10:00']);
        $this->assertTrue($slots['10:30']);
    }

    public function test_キャンセル済みの枠は予約できる(): void
    {
        $reservation = Reservation::factory()->create(['start_at' => '2026-11-06 10:00:00']);
        $reservation->cancel(CancelledBy::Patient);

        $this->assertTrue($this->availabilityOf('2026-11-06')['10:00']);
    }

    public function test_今から1時間以内の枠と過去の枠は予約できない(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00'));

        $slots = $this->availabilityOf('2026-11-05');

        $this->assertFalse($slots['09:30']); // 過去
        $this->assertFalse($slots['10:00']); // ちょうど今
        $this->assertFalse($slots['10:30']); // 30分後
        $this->assertTrue($slots['11:00']);  // ちょうど1時間後は予約できる
        $this->assertTrue($slots['16:00']);
    }

    public function test_今日から30日後までが予約可能期間(): void
    {
        $this->assertTrue(Availability::isBookableDate(CarbonImmutable::parse('2026-11-05')));
        $this->assertTrue(Availability::isBookableDate(CarbonImmutable::parse('2026-12-05')));
        $this->assertFalse(Availability::isBookableDate(CarbonImmutable::parse('2026-12-06')));
        $this->assertFalse(Availability::isBookableDate(CarbonImmutable::parse('2026-11-04')));
    }

    public function test_予約可能期間の外の枠は予約できない(): void
    {
        $slots = $this->availabilityOf('2026-12-07'); // 31日後より先の月曜

        $this->assertNotEmpty($slots);
        $this->assertNotContains(true, $slots);
    }

    public function test_日をまたいでも予約は正しい日に反映される(): void
    {
        Reservation::factory()->create(['start_at' => '2026-11-06 10:00:00']);

        $days = app(Availability::class)->forDates(
            CarbonImmutable::parse('2026-11-06'),
            CarbonImmutable::parse('2026-11-09'),
        );

        $this->assertFalse(array_column($days['2026-11-06']['am'], 'available', 'time')['10:00']);
        $this->assertTrue(array_column($days['2026-11-09']['am'], 'available', 'time')['10:00']);
    }

    public function test_休診日は枠がない(): void
    {
        $day = app(Availability::class)->forDates(
            CarbonImmutable::parse('2026-11-08'),
            CarbonImmutable::parse('2026-11-08'),
        )['2026-11-08']; // 日曜

        $this->assertTrue($day['is_closed']);
        $this->assertNull($day['am']);
        $this->assertNull($day['pm']);
    }
}
