<?php

namespace Tests\Feature;

use App\Enums\MailStatus;
use App\Enums\MailType;
use App\Mail\ReservationReceived;
use App\Models\MailLog;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * 予約完了メールの送信と、送信結果の記録（mail_logs）を確かめる
 */
class ReservationMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00')); // 木曜
        config([
            'clinic.name' => 'テスト接骨院',
            'clinic.phone' => '000-111-2222',
            'clinic.address' => 'テスト県テスト市1-2-3',
            'clinic.frontend_url' => 'https://clinic.example.test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'patient_name' => '山田 太郎',
            'patient_phone' => '090-1234-5678',
            'patient_email' => 'taro@example.com',
            'patient_email_confirmation' => 'taro@example.com',
            'visit_type' => 'first',
            'treatment_request' => '腰痛',
            'start_at' => '2026-11-06 10:00', // 金曜
            ...$overrides,
        ];
    }

    public function test_メールアドレスがあれば予約完了メールを1通送り記録する(): void
    {
        Mail::fake(); // 実際には送らず「送ったこと」だけを記録する

        $this->postJson('/api/reservations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('mail_sent', true);

        Mail::assertSent(ReservationReceived::class, fn (ReservationReceived $mail) => $mail->hasTo('taro@example.com'));
        Mail::assertSentCount(1);

        $log = MailLog::sole();
        $this->assertSame(Reservation::sole()->id, $log->reservation_id);
        $this->assertSame(MailType::Reserved, $log->type);
        $this->assertSame(MailStatus::Sent, $log->status);
        $this->assertSame('taro@example.com', $log->to_email);
        $this->assertNotNull($log->sent_at);
    }

    public function test_メールアドレスがなければ送らない(): void
    {
        Mail::fake();

        $this->postJson('/api/reservations', $this->payload([
            'patient_email' => '',
            'patient_email_confirmation' => '',
        ]))
            ->assertCreated()
            ->assertJsonPath('has_email', false)
            ->assertJsonPath('mail_sent', false);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('mail_logs', 0);
    }

    public function test_メールの送信に失敗しても予約は成立し失敗を記録する(): void
    {
        // メールの送信部分を、わざとエラーにする
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP サーバーに接続できません'));
        // エラーログに個人情報（メールアドレス・氏名）が含まれないことも確かめる
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                $logged = $message.json_encode($context, JSON_UNESCAPED_UNICODE);

                return ! str_contains($logged, 'taro@example.com') && ! str_contains($logged, '山田');
            });

        $this->postJson('/api/reservations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('has_email', true)
            ->assertJsonPath('mail_sent', false);

        $this->assertDatabaseCount('reservations', 1);

        $log = MailLog::sole();
        $this->assertSame(MailStatus::Failed, $log->status);
        $this->assertSame('SMTP サーバーに接続できません', $log->error_message);
        $this->assertNull($log->sent_at);
    }

    public function test_メールの本文に予約内容と予約管理ページのリンクが入っている(): void
    {
        Mail::fake();

        $token = $this->postJson('/api/reservations', $this->payload())->json('manage_token');

        Mail::assertSent(ReservationReceived::class, function (ReservationReceived $mail) use ($token) {
            $mail->assertHasSubject('【テスト接骨院】ご予約を承りました');
            $mail->assertSeeInText('山田 太郎 様');
            $mail->assertSeeInText('2026年11月6日（金）10:00〜10:30');
            $mail->assertSeeInText('初回／再来　：初めて');
            $mail->assertSeeInText('施術希望内容：腰痛');
            $mail->assertSeeInText("https://clinic.example.test/reservation/{$token}");
            $mail->assertSeeInText('予約時刻の1時間前まで');
            $mail->assertSeeInText('住所：テスト県テスト市1-2-3');
            $mail->assertSeeInText('電話：000-111-2222');

            return true;
        });
    }

    public function test_施術希望内容がなければなしと表示する(): void
    {
        Mail::fake();

        $this->postJson('/api/reservations', $this->payload(['treatment_request' => '', 'visit_type' => 'return']));

        Mail::assertSent(ReservationReceived::class, function (ReservationReceived $mail) {
            $mail->assertSeeInText('初回／再来　：2回目以降');
            $mail->assertSeeInText('施術希望内容：（なし）');

            return true;
        });
    }

    public function test_本文の記号はそのまま表示する(): void
    {
        Mail::fake();

        $this->postJson('/api/reservations', $this->payload(['treatment_request' => '腰痛 & 肩こり <右>']));

        Mail::assertSent(ReservationReceived::class, function (ReservationReceived $mail) {
            // テキストメールなので「&amp;」のような HTML 用の変換をしない
            $mail->assertSeeInText('腰痛 & 肩こり <右>', false);

            return true;
        });
    }
}
