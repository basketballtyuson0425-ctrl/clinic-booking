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
 * 週のカレンダー・ダッシュボード用の予定表（GET /api/admin/schedule）を確かめる
 */
class AdminScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        // 木曜。この週は 11/3（火）が文化の日、11/4 が第1水曜（休診）、11/7 が土曜（午前のみ）、11/8 が日曜（休診）
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00'));
    }

    private function login(): void
    {
        $this->actingAs(User::factory()->create(), 'web'); // 院長としてログインした状態にする
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reservation(string $startAt, array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'patient_name' => '山田 太郎',
            'patient_phone' => '090-1234-5678',
            'patient_email' => 'taro@example.com',
            ...$attributes,
            'start_at' => $startAt,
        ]);
    }

    public function test_ログインしていなければ401(): void
    {
        $this->getJson('/api/admin/schedule?from=2026-11-02')->assertUnauthorized();
    }

    public function test_指定した日から7日分の営業時間を返す(): void
    {
        $this->login();

        $this->getJson('/api/admin/schedule?from=2026-11-02')
            ->assertOk()
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.date', '2026-11-02')
            ->assertJsonPath('days.0.is_closed', false)
            ->assertJsonPath('days.0.am', ['start' => '09:00', 'end' => '13:00'])
            ->assertJsonPath('days.0.pm', ['start' => '16:00', 'end' => '19:00'])
            ->assertJsonPath('days.0.holiday_name', null)
            // 祝日は休診で、祝日の名前も返す
            ->assertJsonPath('days.1.is_closed', true)
            ->assertJsonPath('days.1.holiday_name', '文化の日')
            // 第1水曜は休診
            ->assertJsonPath('days.2.is_closed', true)
            // 土曜は午前のみ
            ->assertJsonPath('days.5.am', ['start' => '09:00', 'end' => '13:00'])
            ->assertJsonPath('days.5.pm', null)
            ->assertJsonPath('days.6.date', '2026-11-08')
            ->assertJsonPath('days.6.is_closed', true);
    }

    public function test_日数を指定すると最大14日分を返す(): void
    {
        $this->login();

        $this->getJson('/api/admin/schedule?from=2026-11-02&days=14')
            ->assertOk()
            ->assertJsonCount(14, 'days')
            ->assertJsonPath('days.13.date', '2026-11-15');
    }

    public function test_期間内の予約中の予約を日ごとに時刻の順で返しキャンセル済みと期間外は含めない(): void
    {
        $this->login();
        $this->reservation('2026-11-05 16:00');
        $this->reservation('2026-11-05 09:00', ['patient_name' => '佐藤 花子']);
        $this->reservation('2026-11-02 10:00', ['patient_name' => '月曜 太郎']); // 終わった予約（来院済み）
        // キャンセルされた枠は空き枠なので、予定表には出さない
        $this->reservation('2026-11-06 10:00', ['patient_name' => '取消 次郎'])->cancel(CancelledBy::Patient);
        $this->reservation('2026-11-01 10:00', ['patient_name' => '前週 花子']); // 期間の前
        $this->reservation('2026-11-09 10:00', ['patient_name' => '翌週 花子']); // 期間の後

        $days = $this->getJson('/api/admin/schedule?from=2026-11-02')->assertOk()->json('days');
        $startAts = array_map(fn (array $day) => array_column($day['reservations'], 'start_at'), $days);

        $this->assertSame([
            ['2026-11-02 10:00'],
            [],
            [],
            ['2026-11-05 09:00', '2026-11-05 16:00'],
            [],
            [],
            [],
        ], $startAts);
        $this->assertTrue($days[0]['reservations'][0]['is_past']);
        $this->assertSame('佐藤 花子', $days[3]['reservations'][0]['patient_name']);
    }

    public function test_要連絡の印と最後に送ったメールの結果を付ける(): void
    {
        $this->login();
        $outside = $this->reservation('2026-11-06 19:00'); // 後半枠は 19:00 まで。営業時間から外れている
        $failed = $this->reservation('2026-11-06 10:00', ['patient_name' => '佐藤 花子']);
        MailLog::create(['reservation_id' => $failed->id, 'type' => MailType::Reserved, 'to_email' => 'taro@example.com', 'status' => MailStatus::Failed, 'error_message' => '接続できません']);

        $this->getJson('/api/admin/schedule?from=2026-11-02')
            ->assertJsonPath('days.4.reservations.0.id', $failed->id)
            ->assertJsonPath('days.4.reservations.0.needs_contact', false)
            ->assertJsonPath('days.4.reservations.0.last_mail.status', 'failed')
            ->assertJsonPath('days.4.reservations.1.id', $outside->id)
            ->assertJsonPath('days.4.reservations.1.needs_contact', true)
            ->assertJsonPath('days.4.reservations.1.last_mail', null);
    }

    public function test_日付の形や日数が正しくないと422(): void
    {
        $this->login();

        $this->getJson('/api/admin/schedule')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson('/api/admin/schedule?from=abc')->assertUnprocessable()->assertJsonValidationErrors('from');
        // 存在しない日付（3月2日に繰り上げない）
        $this->getJson('/api/admin/schedule?from=2026-02-30')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson('/api/admin/schedule?from=2026-11-02&days=0')->assertUnprocessable()->assertJsonValidationErrors('days');
        $this->getJson('/api/admin/schedule?from=2026-11-02&days=15')->assertUnprocessable()->assertJsonValidationErrors('days');
        $this->getJson('/api/admin/schedule?from=2026-11-02&days=abc')->assertUnprocessable()->assertJsonValidationErrors('days');
    }

    public function test_個人情報を伏せずに返し内部の値は返さずキャッシュさせない(): void
    {
        $this->login();
        $reservation = $this->reservation('2026-11-06 10:00');
        $reservation->issueManageToken();
        $reservation->save();

        $response = $this->getJson('/api/admin/schedule?from=2026-11-02')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('days.4.reservations.0.patient_phone', '090-1234-5678')
            ->assertJsonPath('days.4.reservations.0.patient_email', 'taro@example.com');

        $body = $response->getContent();
        $this->assertStringNotContainsString('manage_token_hash', $body);
        $this->assertStringNotContainsString($reservation->manage_token_hash, $body);
        $this->assertStringNotContainsString('name_key', $body);
    }
}
