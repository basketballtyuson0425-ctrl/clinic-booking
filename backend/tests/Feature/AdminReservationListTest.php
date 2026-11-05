<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\MailStatus;
use App\Enums\MailType;
use App\Enums\ReservationStatus;
use App\Models\MailLog;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理画面の予約一覧（GET /api/admin/reservations）と、ログインしていないときの拒否を確かめる
 */
class AdminReservationListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00')); // 木曜
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

    /**
     * @return list<string> 一覧に出た予約の日時
     */
    private function listedStartAts(string $query = ''): array
    {
        return array_column($this->getJson("/api/admin/reservations{$query}")->assertOk()->json('data'), 'start_at');
    }

    // ---- ログイン ----

    public function test_ログインしていなければ管理画面の窓口はすべて401(): void
    {
        $reservation = $this->reservation('2026-11-06 10:00');

        $this->getJson('/api/admin/reservations')->assertUnauthorized();
        $this->postJson('/api/admin/reservations', [])->assertUnauthorized();
        $this->getJson("/api/admin/reservations/{$reservation->id}")->assertUnauthorized();
        $this->patchJson("/api/admin/reservations/{$reservation->id}", [])->assertUnauthorized();
        $this->postJson("/api/admin/reservations/{$reservation->id}/cancel", [])->assertUnauthorized();
        $this->postJson("/api/admin/reservations/{$reservation->id}/resend-mail")->assertUnauthorized();
        $this->getJson('/api/admin/mail-logs')->assertUnauthorized();
        $this->getJson('/api/admin/availability/2026-11-06')->assertUnauthorized();
        // 存在しない予約でも、ログインしていなければ 404 ではなく 401（予約があるかどうかを教えない）
        $this->getJson('/api/admin/reservations/99999')->assertUnauthorized();

        $this->assertSame(ReservationStatus::Reserved, $reservation->fresh()->status);
    }

    // ---- 一覧 ----

    public function test_日時の早い順に今日以降の予約を返す(): void
    {
        $this->login();
        $this->reservation('2026-11-09 10:00');
        $this->reservation('2026-11-04 10:00', ['patient_name' => '昨日 太郎']); // 昨日
        $this->reservation('2026-11-05 09:00', ['patient_name' => '今日 花子']); // 今日
        $this->reservation('2026-11-06 16:00');

        $this->assertSame(['2026-11-05 09:00', '2026-11-06 16:00', '2026-11-09 10:00'], $this->listedStartAts());
    }

    public function test_過去も表示を指定すると過去の予約も返す(): void
    {
        $this->login();
        $this->reservation('2026-11-06 16:00');
        $this->reservation('2026-11-04 10:00', ['patient_name' => '昨日 太郎']);

        $this->assertSame(['2026-11-04 10:00', '2026-11-06 16:00'], $this->listedStartAts('?include_past=1'));
    }

    public function test_日付を指定するとその日だけを返し過去の日付も指定できる(): void
    {
        $this->login();
        $this->reservation('2026-11-06 10:00');
        $this->reservation('2026-11-06 18:30', ['patient_name' => '夕方 花子']);
        $this->reservation('2026-11-09 10:00');
        $this->reservation('2026-11-04 10:00', ['patient_name' => '昨日 太郎']);

        $this->assertSame(['2026-11-06 10:00', '2026-11-06 18:30'], $this->listedStartAts('?date=2026-11-06'));
        $this->assertSame(['2026-11-04 10:00'], $this->listedStartAts('?date=2026-11-04'));
    }

    public function test_氏名の一部で探せて空白や全角半角の違いは無視する(): void
    {
        $this->login();
        $this->reservation('2026-11-06 10:00', ['patient_name' => '山田 太郎']);
        $this->reservation('2026-11-06 10:30', ['patient_name' => '佐藤 花子']);
        $this->reservation('2026-11-06 11:00', ['patient_name' => 'Ｔａｒｏ Ｓｍｉｔｈ']);

        $this->assertSame(['2026-11-06 10:00'], $this->listedStartAts('?name='.urlencode('田太')));
        $this->assertSame(['2026-11-06 10:00'], $this->listedStartAts('?name='.urlencode('山田　太郎')));
        $this->assertSame(['2026-11-06 11:00'], $this->listedStartAts('?name='.urlencode('taro smith')));
    }

    public function test_検索語の百分率記号と下線はただの文字として扱う(): void
    {
        $this->login();
        $this->reservation('2026-11-06 10:00', ['patient_name' => '山田 太郎']);
        $this->reservation('2026-11-06 10:30', ['patient_name' => '100%さん']);

        // % や _ が「何でも一致」として働くと、山田さんも見つかってしまう
        $this->assertSame(['2026-11-06 10:30'], $this->listedStartAts('?name='.urlencode('%')));
        $this->assertSame([], $this->listedStartAts('?name='.urlencode('_')));
        $this->assertSame([], $this->listedStartAts('?name='.urlencode('山%郎')));
    }

    public function test_電話番号の一部で探せてハイフンの有無は無視する(): void
    {
        $this->login();
        $this->reservation('2026-11-06 10:00', ['patient_phone' => '090-1234-5678']);
        $this->reservation('2026-11-06 10:30', ['patient_name' => '佐藤 花子', 'patient_phone' => '03-9876-5432']);

        $this->assertSame(['2026-11-06 10:00'], $this->listedStartAts('?phone=5678'));
        $this->assertSame(['2026-11-06 10:00'], $this->listedStartAts('?phone=1234-5678'));
        $this->assertSame(['2026-11-06 10:30'], $this->listedStartAts('?phone='.urlencode('０３９８７６')));
    }

    public function test_電話番号に数字とハイフン以外を入れると422(): void
    {
        $this->login();

        $this->getJson('/api/admin/reservations?phone=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => '電話番号は数字とハイフンで入力してください。']);
    }

    public function test_状態で絞り込める(): void
    {
        $this->login();
        $this->reservation('2026-11-06 10:00');
        $this->reservation('2026-11-06 10:30', ['patient_name' => '佐藤 花子'])->cancel(CancelledBy::Patient);

        $this->assertSame(['2026-11-06 10:00'], $this->listedStartAts('?status=reserved'));
        $this->assertSame(['2026-11-06 10:30'], $this->listedStartAts('?status=cancelled'));
        $this->getJson('/api/admin/reservations?status=unknown')->assertUnprocessable();
    }

    public function test_来院済みで絞ると終了時刻を過ぎた予約中の予約を過去の分も含めて返す(): void
    {
        $this->login();
        $this->reservation('2026-11-04 10:00', ['patient_name' => '昨日 太郎']);
        $this->reservation('2026-11-05 07:30', ['patient_name' => '早朝 花子']); // 今日の 8:00 に終わった
        $this->reservation('2026-11-04 10:30', ['patient_name' => '取消 次郎'])->cancel(CancelledBy::Admin);
        $this->reservation('2026-11-06 10:00'); // まだ来ていない

        // 「過去の予約も表示」を付けなくても、過去の来院済みを返す
        $this->assertSame(['2026-11-04 10:00', '2026-11-05 07:30'], $this->listedStartAts('?status=visited'));
    }

    public function test_予約中で絞ると来院済みは含めない(): void
    {
        $this->login();
        $this->reservation('2026-11-04 10:00', ['patient_name' => '昨日 太郎']);
        $this->reservation('2026-11-05 07:30', ['patient_name' => '早朝 花子']);
        $this->reservation('2026-11-06 10:00');

        $this->assertSame(['2026-11-06 10:00'], $this->listedStartAts('?status=reserved&include_past=1'));
        $this->assertSame(['2026-11-06 10:00'], $this->listedStartAts('?status=reserved'));
    }

    public function test_50件ずつに分けて返す(): void
    {
        $this->login();
        // 30分ずつずらして51件入れる（一覧の並び順の確認用なので、営業時間かどうかは問わない）
        $startAt = CarbonImmutable::parse('2026-11-06 09:00');
        for ($i = 0; $i < 51; $i++) {
            $this->reservation($startAt->addMinutes(30 * $i)->format('Y-m-d H:i'), ['patient_name' => "患者{$i}"]);
        }

        $this->getJson('/api/admin/reservations')
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.total', 51)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.patient_name', '患者0');

        $this->getJson('/api/admin/reservations?page=2')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_name', '患者50');
    }

    public function test_最後に送ったメールの結果を付ける(): void
    {
        $this->login();
        $withMail = $this->reservation('2026-11-06 10:00');
        MailLog::create(['reservation_id' => $withMail->id, 'type' => MailType::Reserved, 'to_email' => 'taro@example.com', 'status' => MailStatus::Sent, 'sent_at' => now()]);
        MailLog::create(['reservation_id' => $withMail->id, 'type' => MailType::Changed, 'to_email' => 'taro@example.com', 'status' => MailStatus::Failed, 'error_message' => '接続できません']);
        $this->reservation('2026-11-06 10:30', ['patient_name' => '佐藤 花子', 'patient_email' => null]);

        $this->getJson('/api/admin/reservations')
            ->assertJsonPath('data.0.last_mail.type', 'changed')
            ->assertJsonPath('data.0.last_mail.status', 'failed')
            ->assertJsonPath('data.1.last_mail', null);
    }

    public function test_個人情報を伏せずに返し内部の値は返さずキャッシュさせない(): void
    {
        $this->login();
        $reservation = $this->reservation('2026-11-06 10:00');
        $reservation->issueManageToken();
        $reservation->save();

        $response = $this->getJson('/api/admin/reservations')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.0.patient_phone', '090-1234-5678')
            ->assertJsonPath('data.0.patient_email', 'taro@example.com')
            ->assertJsonPath('data.0.has_manage_url', true)
            ->assertJsonPath('data.0.is_past', false);

        $body = $response->getContent();
        $this->assertStringNotContainsString('manage_token_hash', $body);
        $this->assertStringNotContainsString($reservation->manage_token_hash, $body);
        $this->assertStringNotContainsString('name_key', $body);
    }
}
