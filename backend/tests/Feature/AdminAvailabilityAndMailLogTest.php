<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\MailStatus;
use App\Enums\MailType;
use App\Models\MailLog;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 院長用の空き枠 API（/api/admin/availability/{date}）と、メールの送信記録（/api/admin/mail-logs）を確かめる
 */
class AdminAvailabilityAndMailLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:40:00')); // 木曜
        $this->actingAs(User::factory()->create(), 'web'); // 院長としてログインした状態にする
    }

    // ---- 空き枠 ----

    public function test_枠ごとに予約済みかと選べるかを返す(): void
    {
        Reservation::factory()->create(['start_at' => '2026-11-06 10:00']);
        Reservation::factory()->create(['start_at' => '2026-11-06 10:30'])->cancel(CancelledBy::Patient);

        $response = $this->getJson('/api/admin/availability/2026-11-06')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('date', '2026-11-06')
            ->assertJsonPath('is_closed', false);

        $am = collect($response->json('am'))->keyBy('time');
        $this->assertSame(['time' => '10:00', 'reserved' => true, 'available' => false], $am['10:00']);
        // キャンセル済みの予約は枠を使わない
        $this->assertSame(['time' => '10:30', 'reserved' => false, 'available' => true], $am['10:30']);
        $this->assertCount(6, $response->json('pm')); // 16:00〜18:30
    }

    public function test_今日の過ぎた枠は選べず1時間以内の枠は選べる(): void
    {
        $am = collect($this->getJson('/api/admin/availability/2026-11-05')->json('am'))->keyBy('time');

        // 今は 09:40
        $this->assertFalse($am['09:00']['available']);
        $this->assertFalse($am['09:30']['available']);
        // 患者は1時間前までしか予約できないが、院長は選べる
        $this->assertTrue($am['10:00']['available']);
    }

    public function test_31日より先の日も返し休診日は枠なし(): void
    {
        $this->getJson('/api/admin/availability/2026-12-10')
            ->assertOk()
            ->assertJsonPath('is_closed', false)
            ->assertJsonPath('am.0.available', true);

        $this->getJson('/api/admin/availability/2026-11-08') // 日曜
            ->assertOk()
            ->assertJsonPath('is_closed', true)
            ->assertJsonPath('am', null)
            ->assertJsonPath('pm', null);
    }

    public function test_過去の日付と存在しない日付は422(): void
    {
        $this->getJson('/api/admin/availability/2026-11-04')
            ->assertUnprocessable()
            ->assertJsonPath('message', '過去の日付は選べません。');
        $this->getJson('/api/admin/availability/2026-02-30')
            ->assertUnprocessable()
            ->assertJsonPath('message', '日付が正しくありません。');
        $this->getJson('/api/admin/availability/tomorrow')->assertNotFound();
    }

    // ---- メールの送信記録 ----

    private function mailLog(Reservation $reservation, MailType $type, MailStatus $status): MailLog
    {
        return MailLog::create([
            'reservation_id' => $reservation->id,
            'type' => $type,
            'to_email' => 'taro@example.com',
            'status' => $status,
            'error_message' => $status === MailStatus::Failed ? '接続できません' : null,
            'sent_at' => $status === MailStatus::Sent ? now() : null,
        ]);
    }

    public function test_送信記録を新しい順に予約の情報と一緒に返す(): void
    {
        $reservation = Reservation::factory()->create(['patient_name' => '山田 太郎', 'start_at' => '2026-11-06 10:00']);
        $this->mailLog($reservation, MailType::Reserved, MailStatus::Sent);
        $this->mailLog($reservation, MailType::Changed, MailStatus::Failed);
        $this->mailLog($reservation, MailType::Resent, MailStatus::Sent);

        $this->getJson('/api/admin/mail-logs')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.type', 'resent')
            ->assertJsonPath('data.1.type', 'changed')
            ->assertJsonPath('data.1.status', 'failed')
            ->assertJsonPath('data.1.error_message', '接続できません')
            ->assertJsonPath('data.2.type', 'reserved')
            ->assertJsonPath('data.0.reservation', [
                'id' => $reservation->id,
                'start_at' => '2026-11-06 10:00',
                'patient_name' => '山田 太郎',
            ]);
    }

    public function test_送信失敗だけに絞り込める(): void
    {
        $reservation = Reservation::factory()->create();
        $this->mailLog($reservation, MailType::Reserved, MailStatus::Sent);
        $failed = $this->mailLog($reservation, MailType::Changed, MailStatus::Failed);

        $this->getJson('/api/admin/mail-logs?failed_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $failed->id);
    }
}
