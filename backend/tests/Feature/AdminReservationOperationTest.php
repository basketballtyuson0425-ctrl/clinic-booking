<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\MailStatus;
use App\Enums\MailType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Mail\ReservationCancelled;
use App\Mail\ReservationChanged;
use App\Mail\ReservationReceived;
use App\Models\MailLog;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * 院長による予約の登録・詳細・変更・キャンセル・メールの再送を確かめる
 */
class AdminReservationOperationTest extends TestCase
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
        $this->actingAs(User::factory()->create(), 'web'); // 院長としてログインした状態にする
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
            'visit_type' => 'first',
            'treatment_request' => '腰痛',
            'start_at' => '2026-11-06 10:00', // 金曜
            'send_mail' => true,
            ...$overrides,
        ];
    }

    /**
     * 患者がネット予約した予約を1件作り、予約管理 URL の鍵と一緒に返す
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Reservation, 1: string}
     */
    private function reservationWithToken(array $attributes = []): array
    {
        $reservation = Reservation::factory()->make([
            'patient_name' => '山田 太郎',
            'patient_phone' => '090-1234-5678',
            'patient_email' => 'taro@example.com',
            'visit_type' => 'first',
            'treatment_request' => '腰痛',
            'start_at' => '2026-11-06 10:00',
            ...$attributes,
        ]);
        $token = $reservation->issueManageToken();
        $reservation->save();

        return [$reservation, $token];
    }

    // ---- 登録 ----

    public function test_院長が予約を登録すると確認メールを送り鍵を発行する(): void
    {
        $response = $this->postJson('/api/admin/reservations', $this->payload())
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('mail_sent', true)
            ->assertJsonPath('data.start_at', '2026-11-06 10:00')
            ->assertJsonPath('data.source', 'admin')
            ->assertJsonPath('data.has_manage_url', true)
            ->assertJsonPath('data.last_mail.type', 'reserved');

        $reservation = Reservation::sole();
        $this->assertSame(ReservationSource::Admin, $reservation->source);
        $this->assertSame($response->json('data.id'), $reservation->id);
        // 鍵そのものは応答に含めない（メールでだけ患者に知らせる）
        $this->assertArrayNotHasKey('manage_token', $response->json());

        Mail::assertSent(ReservationReceived::class, function (ReservationReceived $mail) {
            $mail->assertHasSubject('【テスト接骨院】ご予約を承りました');
            // 電話などで受けた予約なので「ネット予約」とは書かない
            $mail->assertSeeInText('テスト接骨院です。ご予約を承りました。');
            $mail->assertDontSeeInText('ネット予約');
            $mail->assertSeeInText('https://clinic.example.test/reservation/');

            return $mail->hasTo('taro@example.com');
        });

        // メールに載った URL の鍵で、患者の予約管理ページを開ける
        $token = $this->tokenFromLastMail(ReservationReceived::class);
        $this->getJson("/api/manage/{$token}")->assertOk()->assertJsonPath('reservation.patient_name', '山田 太郎');
    }

    public function test_確認メールを送らない指定なら送らず鍵も発行しない(): void
    {
        $this->postJson('/api/admin/reservations', $this->payload(['send_mail' => false]))
            ->assertCreated()
            ->assertJsonPath('mail_sent', false)
            ->assertJsonPath('data.has_manage_url', false);

        $this->assertNull(Reservation::sole()->manage_token_hash);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('mail_logs', 0);
    }

    public function test_メールアドレスがなければ送らず鍵も発行しない(): void
    {
        $this->postJson('/api/admin/reservations', $this->payload(['patient_email' => null]))
            ->assertCreated()
            ->assertJsonPath('mail_sent', false)
            ->assertJsonPath('data.patient_email', null)
            ->assertJsonPath('data.has_manage_url', false);

        $this->assertNull(Reservation::sole()->manage_token_hash);
        Mail::assertNothingSent();
    }

    public function test_院長は31日より先と1時間以内の枠も登録できる(): void
    {
        $this->postJson('/api/admin/reservations', $this->payload(['start_at' => '2026-12-10 10:00']))->assertCreated();
        // 今は 08:30。09:00 の枠は1時間を切っているので患者は予約できないが、院長はできる
        $this->postJson('/api/admin/reservations', $this->payload([
            'patient_name' => '佐藤 花子',
            'start_at' => '2026-11-05 09:00',
        ]))->assertCreated();

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_営業時間外と過去の日時は422(): void
    {
        $outside = [
            '2026-11-06 13:00', // 午前の終了時刻
            '2026-11-06 10:15', // 30分枠の途中
            '2026-11-08 10:00', // 日曜（休診）
            '2026-11-05 08:00', // 過去（今は 08:30）
            '2026-11-04 10:00', // 昨日
        ];

        foreach ($outside as $startAt) {
            $this->postJson('/api/admin/reservations', $this->payload(['start_at' => $startAt]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['start_at' => '選択した日時は予約できません。営業時間内で、今より後の枠を選んでください。']);
        }

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_埋まっている枠と同じ人の同じ日は409(): void
    {
        $this->reservationWithToken();

        $this->postJson('/api/admin/reservations', $this->payload(['patient_name' => '佐藤 花子']))
            ->assertConflict()
            ->assertJsonPath('reason', 'slot_taken');

        $this->postJson('/api/admin/reservations', $this->payload(['start_at' => '2026-11-06 16:00']))
            ->assertConflict()
            ->assertJsonPath('reason', 'same_day')
            ->assertJsonPath('message', '同じ日に、同じ方（氏名と電話番号が同じ）の予約がすでにあります。');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_登録の入力チェック(): void
    {
        $this->postJson('/api/admin/reservations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_name', 'patient_phone', 'visit_type', 'start_at', 'send_mail']);

        // 院長の登録では、メールアドレスの確認欄はいらない
        $this->postJson('/api/admin/reservations', $this->payload(['patient_phone' => '12345']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_phone'])
            ->assertJsonMissingValidationErrors(['patient_email']);
    }

    // ---- 詳細 ----

    public function test_詳細はメールの送信記録を新しい順に付けて返す(): void
    {
        [$reservation] = $this->reservationWithToken();
        MailLog::create(['reservation_id' => $reservation->id, 'type' => MailType::Reserved, 'to_email' => 'taro@example.com', 'status' => MailStatus::Sent, 'sent_at' => now()]);
        MailLog::create(['reservation_id' => $reservation->id, 'type' => MailType::Changed, 'to_email' => 'taro@example.com', 'status' => MailStatus::Failed, 'error_message' => '接続できません']);

        $this->getJson("/api/admin/reservations/{$reservation->id}")
            ->assertOk()
            ->assertJsonPath('data.patient_phone', '090-1234-5678')
            ->assertJsonPath('data.mail_logs.0.type', 'changed')
            ->assertJsonPath('data.mail_logs.0.status', 'failed')
            ->assertJsonPath('data.mail_logs.0.error_message', '接続できません')
            ->assertJsonPath('data.mail_logs.1.type', 'reserved');
    }

    public function test_存在しない予約は404(): void
    {
        $this->getJson('/api/admin/reservations/99999')
            ->assertNotFound()
            ->assertJsonPath('message', '予約が見つかりません。');
        $this->getJson('/api/admin/reservations/abc')->assertNotFound();
    }

    // ---- 変更 ----

    public function test_日時を変更すると同じ予約が書き換わり元の枠が空き患者のページにも出る(): void
    {
        [$reservation, $token] = $this->reservationWithToken();

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-12-10 16:00', 'send_mail' => true])
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('mail_sent', true)
            ->assertJsonPath('data.start_at', '2026-12-10 16:00');

        $this->assertDatabaseCount('reservations', 1);
        // 元の枠（11/6 10:00）が空いたので、別の人が予約できる
        $this->postJson('/api/admin/reservations', $this->payload(['patient_name' => '佐藤 花子']))->assertCreated();
        // 患者の予約管理ページにも、新しい日時が出る
        $this->getJson("/api/manage/{$token}")->assertJsonPath('reservation.start_at', '2026-12-10 16:00');
    }

    public function test_院長が変更したお知らせには予約管理のアドレスを載せない(): void
    {
        [$reservation, $token] = $this->reservationWithToken();

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-11-09 16:00', 'send_mail' => true]);

        Mail::assertSent(ReservationChanged::class, function (ReservationChanged $mail) use ($token) {
            $mail->assertHasSubject('【テスト接骨院】ご予約日時の変更のお知らせ');
            $mail->assertSeeInText('ご予約の日時を、下記のとおり変更いたしました。');
            $mail->assertSeeInText('2026年11月9日（月）16:00〜16:30');
            $mail->assertSeeInText('以前お送りしたメールの予約管理ページの URL から');
            $mail->assertDontSeeInText('/reservation/');
            $mail->assertDontSeeInText($token);

            return true;
        });
        $this->assertSame(MailType::Changed, MailLog::sole()->type);
    }

    public function test_お知らせメールを送らない指定なら日時を変えても送らない(): void
    {
        [$reservation] = $this->reservationWithToken();

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-11-09 16:00', 'send_mail' => false])
            ->assertJsonPath('changed', true)
            ->assertJsonPath('mail_sent', false);

        Mail::assertNothingSent();
    }

    public function test_患者情報だけの修正ではお知らせメールを送らない(): void
    {
        [$reservation] = $this->reservationWithToken();

        $this->patchJson("/api/admin/reservations/{$reservation->id}", [
            'patient_name' => '山田 太朗',
            'patient_phone' => '080-1111-2222',
            'patient_email' => 'taro2@example.com',
            'visit_type' => 'return',
            'treatment_request' => '肩こり',
            'send_mail' => true,
        ])
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('mail_sent', false)
            ->assertJsonPath('data.patient_name', '山田 太朗')
            ->assertJsonPath('data.patient_phone', '080-1111-2222')
            ->assertJsonPath('data.patient_email', 'taro2@example.com')
            ->assertJsonPath('data.visit_type', 'return')
            ->assertJsonPath('data.treatment_request', '肩こり');

        Mail::assertNothingSent();
        // 同じ人の判定に使う値も、新しい氏名・電話番号で計算し直される
        $this->assertSame('08011112222', $reservation->fresh()->phone_key);
    }

    public function test_送られなかった項目は変えずメールアドレスは空にできる(): void
    {
        [$reservation] = $this->reservationWithToken();

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['patient_email' => ''])
            ->assertOk()
            ->assertJsonPath('data.patient_email', null)
            ->assertJsonPath('data.patient_name', '山田 太郎')
            ->assertJsonPath('data.treatment_request', '腰痛');
    }

    public function test_何も変わらなければ変更なしになる(): void
    {
        [$reservation] = $this->reservationWithToken();

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-11-06 10:00', 'patient_name' => '山田 太郎', 'send_mail' => true])
            ->assertOk()
            ->assertJsonPath('changed', false)
            ->assertJsonPath('mail_sent', false);

        $this->patchJson("/api/admin/reservations/{$reservation->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_at' => '変更する内容を指定してください。']);
    }

    public function test_変更先が埋まっていれば409で過去や営業時間外は422(): void
    {
        [$reservation] = $this->reservationWithToken();
        $this->reservationWithToken(['patient_name' => '佐藤 花子', 'start_at' => '2026-11-09 10:00']);

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-11-09 10:00'])
            ->assertConflict()
            ->assertJsonPath('reason', 'slot_taken');
        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-11-05 08:00'])
            ->assertUnprocessable();
        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-11-08 10:00'])
            ->assertUnprocessable();

        $this->assertSame('2026-11-06 10:00', $reservation->fresh()->start_at->format('Y-m-d H:i'));
    }

    public function test_キャンセル済みの予約は変更できない(): void
    {
        [$reservation] = $this->reservationWithToken();
        $reservation->cancel(CancelledBy::Patient);

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['patient_name' => '山田 太朗'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'cancelled');
    }

    public function test_予約時刻を過ぎた予約は日時を変えられないが患者情報は直せる(): void
    {
        [$reservation] = $this->reservationWithToken();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00'));

        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['start_at' => '2026-11-09 10:00'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'past');
        $this->patchJson("/api/admin/reservations/{$reservation->id}", ['treatment_request' => '腰痛（右側）'])
            ->assertOk()
            ->assertJsonPath('changed', true);
    }

    // ---- キャンセル ----

    public function test_院長がキャンセルすると枠が空き院長用の文面のメールを送る(): void
    {
        [$reservation] = $this->reservationWithToken();

        $this->postJson("/api/admin/reservations/{$reservation->id}/cancel", ['send_mail' => true])
            ->assertOk()
            ->assertJsonPath('mail_sent', true)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_by', 'admin');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(CancelledBy::Admin, $reservation->cancelled_by);
        $this->assertNull($reservation->active_start_at);

        Mail::assertSent(ReservationCancelled::class, function (ReservationCancelled $mail) {
            $mail->assertHasSubject('【テスト接骨院】ご予約をキャンセルいたしました');
            $mail->assertSeeInText('下記のご予約をキャンセルいたしました。');
            $mail->assertDontSeeInText('承りました');

            return true;
        });

        // 空いた枠に、別の人が予約できる
        $this->postJson('/api/admin/reservations', $this->payload(['patient_name' => '佐藤 花子']))->assertCreated();
    }

    public function test_キャンセルでメールを送らない指定なら送らない(): void
    {
        [$reservation] = $this->reservationWithToken();

        $this->postJson("/api/admin/reservations/{$reservation->id}/cancel", ['send_mail' => false])
            ->assertOk()
            ->assertJsonPath('mail_sent', false);

        Mail::assertNothingSent();
        $this->postJson("/api/admin/reservations/{$reservation->id}/cancel", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['send_mail']);
    }

    public function test_キャンセル済みと予約時刻を過ぎた予約はキャンセルできない(): void
    {
        [$cancelled] = $this->reservationWithToken();
        $cancelled->cancel(CancelledBy::Patient);
        [$past] = $this->reservationWithToken(['patient_name' => '佐藤 花子', 'start_at' => '2026-11-05 08:00']);

        $this->postJson("/api/admin/reservations/{$cancelled->id}/cancel", ['send_mail' => true])
            ->assertForbidden()
            ->assertJsonPath('reason', 'cancelled');
        $this->postJson("/api/admin/reservations/{$past->id}/cancel", ['send_mail' => true])
            ->assertForbidden()
            ->assertJsonPath('reason', 'past');

        Mail::assertNothingSent();
    }

    // ---- メールの再送 ----

    public function test_再送すると新しい鍵で開け古い鍵は404になる(): void
    {
        [$reservation, $oldToken] = $this->reservationWithToken();

        $this->postJson("/api/admin/reservations/{$reservation->id}/resend-mail")
            ->assertOk()
            ->assertJsonPath('mail_sent', true)
            ->assertJsonPath('data.has_manage_url', true)
            ->assertJsonPath('data.mail_logs.0.type', 'resent');

        Mail::assertSent(ReservationReceived::class, function (ReservationReceived $mail) {
            $mail->assertHasSubject('【テスト接骨院】ご予約内容のお知らせ');
            $mail->assertSeeInText('以前のメールでお知らせした予約管理ページの URL は使えなくなりました。');

            return $mail->resent && $mail->hasTo('taro@example.com');
        });

        $newToken = $this->tokenFromLastMail(ReservationReceived::class);
        $this->assertNotSame($oldToken, $newToken);
        $this->getJson("/api/manage/{$newToken}")->assertOk();
        $this->getJson("/api/manage/{$oldToken}")->assertNotFound();

        $log = MailLog::sole();
        $this->assertSame(MailType::Resent, $log->type);
        $this->assertSame(MailStatus::Sent, $log->status);
    }

    public function test_鍵のない予約に再送すると初めて鍵を発行する(): void
    {
        $reservation = Reservation::factory()->create([
            'patient_email' => 'taro@example.com',
            'start_at' => '2026-11-06 10:00',
            'source' => ReservationSource::Admin,
        ]);
        $this->assertNull($reservation->manage_token_hash);

        $this->postJson("/api/admin/reservations/{$reservation->id}/resend-mail")
            ->assertJsonPath('mail_sent', true)
            ->assertJsonPath('data.has_manage_url', true);

        $this->getJson('/api/manage/'.$this->tokenFromLastMail(ReservationReceived::class))->assertOk();
    }

    public function test_再送に失敗したら鍵を変えず古い鍵が使えるまま(): void
    {
        [$reservation, $oldToken] = $this->reservationWithToken();
        $oldHash = $reservation->manage_token_hash;
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP サーバーに接続できません'));

        $this->postJson("/api/admin/reservations/{$reservation->id}/resend-mail")
            ->assertOk()
            ->assertJsonPath('mail_sent', false)
            ->assertJsonPath('data.mail_logs.0.status', 'failed');

        $this->assertSame($oldHash, $reservation->fresh()->manage_token_hash);
        $this->getJson("/api/manage/{$oldToken}")->assertOk();
    }

    public function test_再送できない予約は403(): void
    {
        [$cancelled] = $this->reservationWithToken();
        $cancelled->cancel(CancelledBy::Patient);
        [$noEmail] = $this->reservationWithToken(['patient_name' => '佐藤 花子', 'patient_email' => null, 'start_at' => '2026-11-06 10:30']);
        [$past] = $this->reservationWithToken(['patient_name' => '鈴木 一郎', 'start_at' => '2026-11-05 08:00']);

        $this->postJson("/api/admin/reservations/{$cancelled->id}/resend-mail")->assertForbidden()->assertJsonPath('reason', 'cancelled');
        $this->postJson("/api/admin/reservations/{$noEmail->id}/resend-mail")->assertForbidden()->assertJsonPath('reason', 'no_email');
        $this->postJson("/api/admin/reservations/{$past->id}/resend-mail")->assertForbidden()->assertJsonPath('reason', 'past');

        Mail::assertNothingSent();
    }

    /**
     * 最後に送ったメールの本文から、予約管理 URL の鍵を取り出す
     *
     * @param  class-string<ReservationReceived>  $mailClass
     */
    private function tokenFromLastMail(string $mailClass): string
    {
        $mail = Mail::sent($mailClass)->last();
        preg_match('#/reservation/([0-9a-f]{64})#', $mail->manageUrl, $matches);

        return $matches[1];
    }
}
