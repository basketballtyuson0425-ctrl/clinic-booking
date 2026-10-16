<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\MailStatus;
use App\Enums\MailType;
use App\Enums\ReservationStatus;
use App\Mail\ReservationCancelled;
use App\Mail\ReservationChanged;
use App\Models\MailLog;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * 予約管理ページの API（/api/manage/{token}）を確かめる
 */
class ManageReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00')); // 木曜
        Mail::fake();
        config([
            'clinic.name' => 'テスト接骨院',
            'clinic.phone' => '000-111-2222',
            'clinic.frontend_url' => 'https://clinic.example.test',
        ]);
    }

    /**
     * 予約を1件作り、予約管理 URL の鍵と一緒に返す
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Reservation, 1: string}
     */
    private function reservationWithToken(array $attributes = []): array
    {
        $reservation = Reservation::factory()->make([
            'patient_name' => '山田 太郎',
            'patient_phone' => '090-1234-5678',
            'patient_email' => 'hanako@example.com',
            'visit_type' => 'first',
            'treatment_request' => '腰痛',
            'start_at' => '2026-11-06 10:00:00', // 金曜
            ...$attributes,
        ]);
        $token = $reservation->issueManageToken();
        $reservation->save();

        return [$reservation, $token];
    }

    // ---- 表示 ----

    public function test_正しい鍵なら予約内容を返す(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->getJson("/api/manage/{$token}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('reservation.start_at', '2026-11-06 10:00')
            ->assertJsonPath('reservation.end_at', '2026-11-06 10:30')
            ->assertJsonPath('reservation.status', 'reserved')
            ->assertJsonPath('reservation.patient_name', '山田 太郎')
            ->assertJsonPath('reservation.visit_type', 'first')
            ->assertJsonPath('reservation.treatment_request', '腰痛')
            ->assertJsonPath('reservation.has_email', true)
            ->assertJsonPath('reservation.can_modify', true)
            ->assertJsonPath('reservation.cannot_modify_reason', null)
            ->assertJsonPath('reservation.modifiable_until', '2026-11-06 09:00');
    }

    public function test_電話番号とメールアドレスは一部を伏せて返す(): void
    {
        [, $token] = $this->reservationWithToken();

        $response = $this->getJson("/api/manage/{$token}")
            ->assertJsonPath('reservation.patient_phone_masked', '090-****-5678')
            ->assertJsonPath('reservation.patient_email_masked', 'ha****@example.com');

        // 伏せていない値や、内部の値は含まれない
        $body = $response->getContent();
        $this->assertStringNotContainsString('1234', $body);
        $this->assertStringNotContainsString('hanako@', $body);
        $this->assertStringNotContainsString('manage_token_hash', $body);
    }

    public function test_違う鍵や形の違う鍵は404(): void
    {
        $this->reservationWithToken();

        $this->getJson('/api/manage/'.str_repeat('a', 64))
            ->assertNotFound()
            ->assertJsonPath('message', 'この予約管理ページは無効か、有効期限が切れています。');
        $this->getJson('/api/manage/abc')->assertNotFound();
        $this->getJson('/api/manage/'.str_repeat('Z', 64))->assertNotFound();
    }

    public function test_予約の終了時刻を過ぎると鍵は無効になる(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:29:59'));
        $this->getJson("/api/manage/{$token}")->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:30:00'));
        $this->getJson("/api/manage/{$token}")->assertNotFound();
    }

    public function test_キャンセル済みの予約も終了時刻までは表示できる(): void
    {
        [$reservation, $token] = $this->reservationWithToken();
        $reservation->cancel(CancelledBy::Admin);

        $this->getJson("/api/manage/{$token}")
            ->assertOk()
            ->assertJsonPath('reservation.status', 'cancelled')
            ->assertJsonPath('reservation.can_modify', false)
            ->assertJsonPath('reservation.cannot_modify_reason', 'cancelled');
    }

    // ---- 予約日時の変更 ----

    public function test_予約日時を変更すると同じ予約が書き換わり元の枠が空く(): void
    {
        [$reservation, $token] = $this->reservationWithToken();

        $this->patchJson("/api/manage/{$token}", ['start_at' => '2026-11-09 16:00'])
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('mail_sent', true)
            ->assertJsonPath('reservation.start_at', '2026-11-09 16:00');

        // 新しい予約を作るのではなく、同じ行を上書きする
        $this->assertDatabaseCount('reservations', 1);
        $updated = Reservation::sole();
        $this->assertSame($reservation->id, $updated->id);
        $this->assertSame('2026-11-09 16:30:00', $updated->end_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 16:00:00', $updated->active_start_at->format('Y-m-d H:i:s'));

        // 元の枠は空き（○）に戻り、新しい枠は埋まる
        $before = collect($this->getJson('/api/availability/2026-11-06')->json('am'))->keyBy('time');
        $after = collect($this->getJson('/api/availability/2026-11-09')->json('pm'))->keyBy('time');
        $this->assertTrue($before['10:00']['available']);
        $this->assertFalse($after['16:00']['available']);

        // 変更後も同じ鍵で開ける
        $this->getJson("/api/manage/{$token}")->assertOk();
    }

    public function test_同じ日の別の時間に移せる(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->patchJson("/api/manage/{$token}", ['start_at' => '2026-11-06 16:30'])
            ->assertOk()
            ->assertJsonPath('reservation.start_at', '2026-11-06 16:30');
    }

    public function test_埋まっている枠には変更できない(): void
    {
        [, $token] = $this->reservationWithToken();
        Reservation::factory()->create(['start_at' => '2026-11-09 16:00:00']);

        $this->patchJson("/api/manage/{$token}", ['start_at' => '2026-11-09 16:00'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'slot_taken');

        $this->assertSame('2026-11-06 10:00', Reservation::where('patient_name', '山田 太郎')->sole()->start_at->format('Y-m-d H:i'));
        Mail::assertNothingSent();
    }

    public function test_予約できない日時には変更できない(): void
    {
        [, $token] = $this->reservationWithToken();

        foreach (['2026-11-08 10:00', '2026-11-06 13:00', '2026-12-07 10:00', '2026-11-05 09:00'] as $startAt) {
            $this->patchJson("/api/manage/{$token}", ['start_at' => $startAt])
                ->assertJsonValidationErrors(['start_at' => '選択した日時は予約できません。別の日時を選んでください。']);
        }
    }

    public function test_施術希望内容だけを変更できる(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->patchJson("/api/manage/{$token}", ['treatment_request' => '肩こり'])
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('reservation.start_at', '2026-11-06 10:00')
            ->assertJsonPath('reservation.treatment_request', '肩こり');

        $this->patchJson("/api/manage/{$token}", ['treatment_request' => ''])
            ->assertOk()
            ->assertJsonPath('reservation.treatment_request', null);
    }

    public function test_何も変わらなければメールは送らない(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->patchJson("/api/manage/{$token}", ['start_at' => '2026-11-06 10:00', 'treatment_request' => '腰痛'])
            ->assertOk()
            ->assertJsonPath('changed', false)
            ->assertJsonPath('mail_sent', false);

        Mail::assertNothingSent();
    }

    public function test_変更する内容がなければエラー(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->patchJson("/api/manage/{$token}", [])
            ->assertJsonValidationErrors(['start_at' => '変更する内容を指定してください。']);
        $this->patchJson("/api/manage/{$token}", ['treatment_request' => str_repeat('あ', 501)])
            ->assertJsonValidationErrors('treatment_request');
    }

    public function test_変更のお知らせメールを送り記録する(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->patchJson("/api/manage/{$token}", ['start_at' => '2026-11-09 16:00', 'treatment_request' => '肩こり']);

        Mail::assertSent(ReservationChanged::class, function (ReservationChanged $mail) use ($token) {
            $mail->assertHasSubject('【テスト接骨院】ご予約の変更を承りました');
            $mail->assertSeeInText('2026年11月9日（月）16:00〜16:30');
            $mail->assertSeeInText('施術希望内容：肩こり');
            $mail->assertSeeInText("https://clinic.example.test/reservation/{$token}");
            $mail->assertSeeInText('電話：000-111-2222');

            return $mail->hasTo('hanako@example.com');
        });

        $log = MailLog::sole();
        $this->assertSame(MailType::Changed, $log->type);
        $this->assertSame(MailStatus::Sent, $log->status);
    }

    // ---- キャンセル ----

    public function test_キャンセルするとキャンセル済みになり枠が空く(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->postJson("/api/manage/{$token}/cancel")
            ->assertOk()
            ->assertJsonPath('mail_sent', true)
            ->assertJsonPath('reservation.status', 'cancelled')
            ->assertJsonPath('reservation.can_modify', false);

        $reservation = Reservation::sole();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(CancelledBy::Patient, $reservation->cancelled_by);
        $this->assertNull($reservation->active_start_at);

        $slots = collect($this->getJson('/api/availability/2026-11-06')->json('am'))->keyBy('time');
        $this->assertTrue($slots['10:00']['available']);
    }

    public function test_キャンセルのお知らせメールを送り記録する(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->postJson("/api/manage/{$token}/cancel");

        Mail::assertSent(ReservationCancelled::class, function (ReservationCancelled $mail) {
            $mail->assertHasSubject('【テスト接骨院】ご予約のキャンセルを承りました');
            $mail->assertSeeInText('2026年11月6日（金）10:00〜10:30');
            $mail->assertSeeInText('https://clinic.example.test/reserve');

            return $mail->hasTo('hanako@example.com');
        });
        $this->assertSame(MailType::Cancelled, MailLog::sole()->type);
    }

    public function test_メールの送信に失敗してもキャンセルは成立する(): void
    {
        [, $token] = $this->reservationWithToken();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP サーバーに接続できません'));

        $this->postJson("/api/manage/{$token}/cancel")
            ->assertOk()
            ->assertJsonPath('mail_sent', false)
            ->assertJsonPath('reservation.status', 'cancelled');

        $this->assertSame(MailStatus::Failed, MailLog::sole()->status);
    }

    // ---- 変更・キャンセルできない場合（403） ----

    public function test_予約時刻の1時間前を過ぎると変更もキャンセルもできない(): void
    {
        [, $token] = $this->reservationWithToken();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00:00'));
        $this->getJson("/api/manage/{$token}")->assertJsonPath('reservation.can_modify', true); // ちょうど1時間前まではできる

        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00:01'));
        $this->getJson("/api/manage/{$token}")
            ->assertJsonPath('reservation.can_modify', false)
            ->assertJsonPath('reservation.cannot_modify_reason', 'deadline_passed');
        $this->patchJson("/api/manage/{$token}", ['treatment_request' => '肩こり'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'deadline_passed');
        $this->postJson("/api/manage/{$token}/cancel")->assertForbidden();

        $this->assertSame(ReservationStatus::Reserved, Reservation::sole()->status);
    }

    public function test_メールアドレスがない予約は確認だけできる(): void
    {
        [, $token] = $this->reservationWithToken(['patient_email' => null]);

        $this->getJson("/api/manage/{$token}")
            ->assertOk()
            ->assertJsonPath('reservation.has_email', false)
            ->assertJsonPath('reservation.patient_email_masked', null)
            ->assertJsonPath('reservation.cannot_modify_reason', 'no_email');
        $this->patchJson("/api/manage/{$token}", ['start_at' => '2026-11-09 16:00'])
            ->assertForbidden()
            ->assertJsonPath('message', 'メールアドレスのご登録がないため、オンラインでの変更・キャンセルはできません。お電話でお願いします。');
        $this->postJson("/api/manage/{$token}/cancel")->assertForbidden();
    }

    public function test_キャンセル済みの予約は変更もキャンセルもできない(): void
    {
        [, $token] = $this->reservationWithToken();
        $this->postJson("/api/manage/{$token}/cancel")->assertOk();

        $this->patchJson("/api/manage/{$token}", ['start_at' => '2026-11-09 16:00'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'cancelled');
        $this->postJson("/api/manage/{$token}/cancel")->assertForbidden();

        Mail::assertSentCount(1); // 最初のキャンセルのメールだけ
    }
}
