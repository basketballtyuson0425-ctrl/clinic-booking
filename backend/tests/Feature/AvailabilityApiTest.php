<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 空き枠 API（GET /api/availability/...）の返す内容を確かめる
 */
class AvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00')); // 木曜
    }

    public function test_日付一覧は今日から30日後までの31日分を返す(): void
    {
        $response = $this->getJson('/api/availability/dates');

        $response->assertOk();
        $dates = $response->json('dates');
        $this->assertCount(31, $dates);
        $this->assertSame(['date' => '2026-11-05', 'is_closed' => false, 'available' => true], $dates[0]);
        $this->assertSame('2026-12-05', $dates[30]['date']);
    }

    public function test_日付一覧で休診日と満席の日を区別できる(): void
    {
        // 11/7（土）の午前8枠をすべて埋める
        foreach (['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30'] as $time) {
            Reservation::factory()->create(['start_at' => "2026-11-07 {$time}:00"]);
        }

        $dates = collect($this->getJson('/api/availability/dates')->json('dates'))->keyBy('date');

        $this->assertSame(['date' => '2026-11-07', 'is_closed' => false, 'available' => false], $dates['2026-11-07']);
        $this->assertSame(['date' => '2026-11-08', 'is_closed' => true, 'available' => false], $dates['2026-11-08']);
    }

    public function test_指定した日の枠を午前と午後に分けて返す(): void
    {
        Reservation::factory()->create(['start_at' => '2026-11-06 09:30:00']);

        $response = $this->getJson('/api/availability/2026-11-06');

        $response->assertOk()
            ->assertJsonPath('date', '2026-11-06')
            ->assertJsonPath('is_closed', false)
            ->assertJsonCount(8, 'am')
            ->assertJsonCount(6, 'pm')
            ->assertJsonPath('am.0', ['time' => '09:00', 'available' => true])
            ->assertJsonPath('am.1', ['time' => '09:30', 'available' => false]);
    }

    public function test_予約者の情報は返さない(): void
    {
        Reservation::factory()->create([
            'patient_name' => '架空 花子',
            'start_at' => '2026-11-06 09:30:00',
        ]);

        // 日本語をそのままの文字で比べられるよう、JSON を作り直してから調べる
        $body = json_encode($this->getJson('/api/availability/2026-11-06')->json(), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('架空', $body);
        $this->assertStringNotContainsString('patient', $body);
    }

    public function test_存在しない日付はエラーになる(): void
    {
        $this->getJson('/api/availability/2026-02-30')->assertStatus(422);
    }

    public function test_予約可能期間の外の日付はエラーになる(): void
    {
        $this->getJson('/api/availability/2026-11-04')->assertStatus(422); // 昨日
        $this->getJson('/api/availability/2026-12-06')->assertStatus(422); // 31日後
    }

    public function test_日付の形ではないアドレスは404になる(): void
    {
        $this->getJson('/api/availability/tomorrow')->assertNotFound();
    }
}
