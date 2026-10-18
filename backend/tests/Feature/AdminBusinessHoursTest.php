<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\MailType;
use App\Enums\ReservationStatus;
use App\Mail\ReservationHoursChanged;
use App\Models\BusinessDayOverride;
use App\Models\MailLog;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WeeklySchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 営業日カレンダー・営業時間の変更・影響の確認・営業時間変更のお知らせ・要連絡を確かめる
 */
class AdminBusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00')); // 木曜
        Mail::fake();
        config(['clinic.name' => 'テスト接骨院', 'clinic.phone' => '000-111-2222']);
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
            'patient_name' => '患者'.str_replace([' ', ':', '-'], '', $startAt),
            'patient_email' => 'patient@example.com',
            ...$attributes,
            'start_at' => $startAt,
        ]);
    }

    /**
     * 11/9（月）の営業時間を「前半 10:00〜12:00、後半 休み」にする内容
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function shortDay(array $overrides = []): array
    {
        return [
            'is_closed' => false,
            'am_start' => '10:00',
            'am_end' => '12:00',
            'pm_start' => null,
            'pm_end' => null,
            ...$overrides,
        ];
    }

    /**
     * @return list<array<string, mixed>> 曜日ごとの設定（今の設定のまま）。$changes で一部の曜日を書き換える
     */
    private function weeklyPayload(array $changes = []): array
    {
        return array_map(
            fn (array $schedule) => [...$schedule, ...($changes[$schedule['day_of_week']] ?? [])],
            $this->getJson('/api/admin/weekly-schedules')->json('schedules'),
        );
    }

    /**
     * @return array<string, mixed> カレンダーの1日分
     */
    private function calendarDay(string $date): array
    {
        $days = $this->getJson('/api/admin/calendar?month='.substr($date, 0, 7))->json('days');

        return collect($days)->firstWhere('date', $date);
    }

    // ---- ログイン ----

    public function test_ログインしていなければ401(): void
    {
        $this->getJson('/api/admin/calendar?month=2026-11')->assertUnauthorized();
        $this->postJson('/api/admin/business-days/2026-11-09/impact', $this->shortDay())->assertUnauthorized();
        $this->putJson('/api/admin/business-days/2026-11-09', $this->shortDay())->assertUnauthorized();
        $this->deleteJson('/api/admin/business-days/2026-11-09')->assertUnauthorized();
        $this->getJson('/api/admin/weekly-schedules')->assertUnauthorized();
        $this->putJson('/api/admin/weekly-schedules', [])->assertUnauthorized();
        $this->postJson('/api/admin/weekly-schedules/impact', [])->assertUnauthorized();

        $this->assertDatabaseCount('business_day_overrides', 0);
    }

    // ---- カレンダー ----

    public function test_カレンダーは1か月分の営業時間と祝日名と予約の件数を返す(): void
    {
        $this->login();
        $this->reservation('2026-11-09 10:00');
        $this->reservation('2026-11-09 16:00');

        $response = $this->getJson('/api/admin/calendar?month=2026-11')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('month', '2026-11')
            ->assertJsonCount(30, 'days');

        $days = collect($response->json('days'))->keyBy('date');
        $this->assertSame(['start' => '09:00', 'end' => '13:00'], $days['2026-11-09']['am']);
        $this->assertSame(2, $days['2026-11-09']['reservation_count']);
        $this->assertSame('weekly', $days['2026-11-09']['source']);
        // 文化の日（祝日）
        $this->assertTrue($days['2026-11-03']['is_closed']);
        $this->assertSame('holiday', $days['2026-11-03']['source']);
        $this->assertSame('文化の日', $days['2026-11-03']['holiday_name']);
        // 日曜
        $this->assertTrue($days['2026-11-08']['is_closed']);
        $this->assertNull($days['2026-11-08']['holiday_name']);

        $this->getJson('/api/admin/calendar?month=2026-13')->assertUnprocessable();
    }

    // ---- 日付ごとの変更 ----

    public function test_予約のない日は確認なしで保存でき患者の空き枠にも反映される(): void
    {
        $this->login();

        $this->putJson('/api/admin/business-days/2026-11-09', $this->shortDay())
            ->assertOk()
            ->assertJsonPath('day.has_override', true)
            ->assertJsonPath('day.am', ['start' => '10:00', 'end' => '12:00'])
            ->assertJsonPath('day.pm', null)
            ->assertJsonPath('affected', []);

        $override = BusinessDayOverride::sole();
        $this->assertSame('10:00:00', $override->am_start);

        $slots = $this->getJson('/api/availability/2026-11-09')->assertOk();
        $this->assertSame(['10:00', '10:30', '11:00', '11:30'], array_column($slots->json('am'), 'time'));
        $this->assertNull($slots->json('pm'));
    }

    public function test_祝日を営業日にできる(): void
    {
        $this->login();

        $this->putJson('/api/admin/business-days/2026-11-23', $this->shortDay())
            ->assertOk()
            ->assertJsonPath('day.is_closed', false)
            ->assertJsonPath('day.source', 'override')
            ->assertJsonPath('day.holiday_name', '勤労感謝の日');
    }

    public function test_休診にすると時刻は保存しない(): void
    {
        $this->login();

        $this->putJson('/api/admin/business-days/2026-11-09', $this->shortDay(['is_closed' => true]))
            ->assertOk()
            ->assertJsonPath('day.is_closed', true);

        $this->assertNull(BusinessDayOverride::sole()->am_start);
    }

    public function test_予約がある日は確認なしだと409で保存しない(): void
    {
        $this->login();
        $this->reservation('2026-11-09 10:00'); // 新しい営業時間の中
        $outside = $this->reservation('2026-11-09 16:00'); // 後半を休みにすると外れる

        $this->putJson('/api/admin/business-days/2026-11-09', $this->shortDay())
            ->assertConflict()
            ->assertJsonPath('reason', 'confirmation_required')
            ->assertJsonPath('impact.reservation_count', 2)
            ->assertJsonCount(1, 'impact.affected')
            ->assertJsonPath('impact.affected.0.id', $outside->id)
            ->assertJsonPath('impact.schedule.pm', null);

        $this->assertDatabaseCount('business_day_overrides', 0);
        Mail::assertNothingSent();
    }

    public function test_影響の確認は保存せずに影響のある予約だけを返す(): void
    {
        $this->login();
        $this->reservation('2026-11-09 10:00');
        $outside = $this->reservation('2026-11-09 16:00', ['patient_name' => '佐藤 花子', 'patient_phone' => '090-1111-2222']);

        $this->postJson('/api/admin/business-days/2026-11-09/impact', $this->shortDay())
            ->assertOk()
            ->assertJsonPath('reservation_count', 2)
            ->assertJsonPath('affected.0', [
                'id' => $outside->id,
                'start_at' => '2026-11-09 16:00',
                'end_at' => '2026-11-09 16:30',
                'patient_name' => '佐藤 花子',
                'patient_phone' => '090-1111-2222',
                'has_email' => true,
            ]);

        $this->assertDatabaseCount('business_day_overrides', 0);
    }

    public function test_確認して保存すると影響のある患者にだけお知らせを送り予約はそのまま残す(): void
    {
        $this->login();
        $inside = $this->reservation('2026-11-09 10:00');
        $outside = $this->reservation('2026-11-09 16:00', ['patient_email' => 'outside@example.com']);
        $noEmail = $this->reservation('2026-11-09 16:30', ['patient_email' => null]);
        $cancelled = $this->reservation('2026-11-09 17:00');
        $cancelled->cancel(CancelledBy::Patient);

        $this->putJson('/api/admin/business-days/2026-11-09', [...$this->shortDay(), 'confirmed' => true, 'send_mail' => true])
            ->assertOk()
            ->assertJsonPath('mail', ['sent' => 1, 'failed' => 0])
            ->assertJsonCount(2, 'affected');

        // メールは、外れた予約でメールアドレスがある人だけ
        Mail::assertSentCount(1);
        Mail::assertSent(ReservationHoursChanged::class, function (ReservationHoursChanged $mail) {
            $mail->assertHasSubject('【テスト接骨院】営業時間変更のお知らせ');
            $mail->assertSeeInText('11月9日（月）の営業時間を変更いたしました。');
            $mail->assertSeeInText('前半：10:00〜12:00');
            $mail->assertSeeInText('後半：休診');
            $mail->assertSeeInText('2026年11月9日（月）16:00〜16:30');
            $mail->assertSeeInText('ご予約はキャンセルされておりません。');

            return $mail->hasTo('outside@example.com');
        });
        $this->assertSame(MailType::HoursChanged, MailLog::sole()->type);

        // 予約は自動でキャンセル・変更しない
        foreach ([$inside, $outside, $noEmail] as $reservation) {
            $this->assertSame(ReservationStatus::Reserved, $reservation->fresh()->status);
        }
        $this->assertSame('2026-11-09 16:00', $outside->fresh()->start_at->format('Y-m-d H:i'));
    }

    public function test_お知らせメールを送らない指定なら送らない(): void
    {
        $this->login();
        $this->reservation('2026-11-09 16:00');

        $this->putJson('/api/admin/business-days/2026-11-09', [...$this->shortDay(), 'confirmed' => true, 'send_mail' => false])
            ->assertOk()
            ->assertJsonPath('mail', ['sent' => 0, 'failed' => 0]);

        Mail::assertNothingSent();
    }

    public function test_通常設定に戻すと個別設定を消し予約があれば確認が必要(): void
    {
        $this->login();
        BusinessDayOverride::create(['date' => '2026-11-09', 'is_closed' => false, 'am_start' => '09:00:00', 'am_end' => '13:00:00', 'pm_start' => '16:00:00', 'pm_end' => '20:00:00']);
        $late = $this->reservation('2026-11-09 19:30'); // 基本の営業時間（19:00 まで）に戻すと外れる

        $this->postJson('/api/admin/business-days/2026-11-09/impact', ['reset' => true])
            ->assertOk()
            ->assertJsonPath('schedule.pm', ['start' => '16:00', 'end' => '19:00'])
            ->assertJsonPath('affected.0.id', $late->id);

        $this->deleteJson('/api/admin/business-days/2026-11-09')->assertConflict();
        $this->assertDatabaseCount('business_day_overrides', 1);

        $this->deleteJson('/api/admin/business-days/2026-11-09', ['confirmed' => true, 'send_mail' => true])
            ->assertOk()
            ->assertJsonPath('day.has_override', false)
            ->assertJsonPath('day.source', 'weekly')
            ->assertJsonPath('mail.sent', 1);

        $this->assertDatabaseCount('business_day_overrides', 0);
    }

    public function test_営業時間の入力チェック(): void
    {
        $this->login();
        $url = '/api/admin/business-days/2026-11-09';

        $this->putJson($url, $this->shortDay(['am_start' => '09:15']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['am_start' => '前半の開始は30分単位（例：9:00、9:30）で入力してください。']);
        $this->putJson($url, $this->shortDay(['am_start' => '12:00', 'am_end' => '10:00']))
            ->assertJsonValidationErrors(['am_end' => '前半の終了は、開始より後の時刻にしてください。']);
        $this->putJson($url, $this->shortDay(['am_end' => null]))
            ->assertJsonValidationErrors(['am_start' => '前半の開始と終了を、両方入力してください。']);
        $this->putJson($url, $this->shortDay(['am_start' => null, 'am_end' => null]))
            ->assertJsonValidationErrors(['am_start']);
        $this->putJson($url, $this->shortDay(['pm_start' => '11:30', 'pm_end' => '18:00']))
            ->assertJsonValidationErrors(['pm_start' => '後半の開始は、前半の終了と同じか、それより後の時刻にしてください。']);
        $this->putJson($url, [])->assertJsonValidationErrors(['is_closed']);

        // 前半の終了と後半の開始が同じなら、続けて営業できる
        $this->putJson($url, $this->shortDay(['pm_start' => '12:00', 'pm_end' => '15:00']))->assertOk();
    }

    public function test_過去の日付と存在しない日付は変更できない(): void
    {
        $this->login();

        $this->putJson('/api/admin/business-days/2026-11-04', $this->shortDay())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => '過去の日付の営業時間は変更できません。']);
        $this->putJson('/api/admin/business-days/2026-02-30', $this->shortDay())
            ->assertJsonValidationErrors(['date' => '日付が正しくありません。']);
        // 今日は変更できる
        $this->putJson('/api/admin/business-days/2026-11-05', $this->shortDay())->assertOk();
    }

    // ---- 要連絡 ----

    public function test_営業時間から外れた予約は要連絡になり日時を直すと消える(): void
    {
        $this->login();
        $inside = $this->reservation('2026-11-09 10:00');
        $outside = $this->reservation('2026-11-09 16:00');
        $this->putJson('/api/admin/business-days/2026-11-09', [...$this->shortDay(), 'confirmed' => true]);

        $list = collect($this->getJson('/api/admin/reservations')->json('data'))->keyBy('id');
        $this->assertFalse($list[$inside->id]['needs_contact']);
        $this->assertTrue($list[$outside->id]['needs_contact']);

        $this->getJson('/api/admin/reservations?needs_contact=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $outside->id);
        $this->getJson("/api/admin/reservations/{$outside->id}")->assertJsonPath('data.needs_contact', true);
        $this->assertSame(1, $this->calendarDay('2026-11-09')['needs_contact_count']);

        // 院長が日時を営業時間内に変えると、要連絡ではなくなる
        $this->patchJson("/api/admin/reservations/{$outside->id}", ['start_at' => '2026-11-09 11:00'])
            ->assertOk()
            ->assertJsonPath('data.needs_contact', false);
        $this->assertSame(0, $this->calendarDay('2026-11-09')['needs_contact_count']);
    }

    public function test_過ぎた予約とキャンセル済みの予約は要連絡にしない(): void
    {
        $this->login();
        BusinessDayOverride::create(['date' => '2026-11-05', 'is_closed' => true]);
        $past = $this->reservation('2026-11-05 08:00'); // 今は 08:30
        $cancelled = $this->reservation('2026-11-05 10:00');
        $cancelled->cancel(CancelledBy::Admin);

        $this->getJson("/api/admin/reservations/{$past->id}")->assertJsonPath('data.needs_contact', false);
        $this->getJson("/api/admin/reservations/{$cancelled->id}")->assertJsonPath('data.needs_contact', false);
    }

    // ---- 曜日ごとの基本設定 ----

    public function test_曜日ごとの設定を返す(): void
    {
        $this->login();

        $schedules = collect($this->getJson('/api/admin/weekly-schedules')->assertOk()->json('schedules'))->keyBy('day_of_week');

        $this->assertCount(7, $schedules);
        $this->assertTrue($schedules[0]['is_closed']); // 日曜
        $this->assertSame('09:00', $schedules[1]['am_start']);
        $this->assertSame([1, 3], $schedules[3]['closed_weeks']); // 水曜は第1・第3が休診
        $this->assertNull($schedules[6]['pm_start']); // 土曜は午前のみ
    }

    public function test_曜日の設定を変えるとき外れる予約があれば確認が必要でお知らせを送れる(): void
    {
        $this->login();
        $late = $this->reservation('2026-11-09 18:30'); // 月曜
        $this->reservation('2026-11-09 10:00');
        // 個別設定がある月曜は、曜日の設定を変えても影響しない
        BusinessDayOverride::create(['date' => '2026-11-16', 'is_closed' => false, 'am_start' => '09:00:00', 'am_end' => '13:00:00', 'pm_start' => '16:00:00', 'pm_end' => '19:00:00']);
        $this->reservation('2026-11-16 18:30');

        $payload = ['schedules' => $this->weeklyPayload([1 => ['pm_end' => '18:00']])];

        $this->postJson('/api/admin/weekly-schedules/impact', $payload)
            ->assertOk()
            ->assertJsonPath('reservation_count', 1)
            ->assertJsonPath('affected.0.id', $late->id);

        $this->putJson('/api/admin/weekly-schedules', $payload)
            ->assertConflict()
            ->assertJsonPath('reason', 'confirmation_required');
        $this->assertSame('19:00:00', WeeklySchedule::where('day_of_week', 1)->value('pm_end'));

        $this->putJson('/api/admin/weekly-schedules', [...$payload, 'confirmed' => true, 'send_mail' => true])
            ->assertOk()
            ->assertJsonPath('mail.sent', 1)
            ->assertJsonPath('affected.0.id', $late->id);

        $this->assertSame('18:00:00', WeeklySchedule::where('day_of_week', 1)->value('pm_end'));
        Mail::assertSent(ReservationHoursChanged::class, fn (ReservationHoursChanged $mail) => $mail->schedule->pm === ['start' => '16:00', 'end' => '18:00']);
        $this->assertSame(ReservationStatus::Reserved, $late->fresh()->status);
    }

    public function test_外れる予約がなければ確認なしで保存でき休診の週も変えられる(): void
    {
        $this->login();

        $this->putJson('/api/admin/weekly-schedules', ['schedules' => $this->weeklyPayload([3 => ['closed_weeks' => [2, 4]]])])
            ->assertOk()
            ->assertJsonPath('affected', []);

        $this->assertSame('2,4', WeeklySchedule::where('day_of_week', 3)->value('closed_weeks_of_month'));
        // 第2水曜（11/11）が休診になり、第1水曜（12/2）が営業日になる
        $this->assertTrue($this->calendarDay('2026-11-11')['is_closed']);
        $this->assertFalse($this->calendarDay('2026-12-02')['is_closed']);
    }

    public function test_曜日ごとの設定の入力チェック(): void
    {
        $this->login();
        $schedules = $this->weeklyPayload();

        $this->putJson('/api/admin/weekly-schedules', ['schedules' => array_slice($schedules, 0, 6)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['schedules']);

        $duplicated = $schedules;
        $duplicated[1]['day_of_week'] = 0;
        $this->putJson('/api/admin/weekly-schedules', ['schedules' => $duplicated])
            ->assertJsonValidationErrors(['schedules.0.day_of_week']);

        $this->putJson('/api/admin/weekly-schedules', ['schedules' => $this->weeklyPayload([2 => ['am_end' => '08:00']])])
            ->assertJsonValidationErrors(['schedules.2.am_end']);

        $this->putJson('/api/admin/weekly-schedules', ['schedules' => $this->weeklyPayload([3 => ['closed_weeks' => [6]]])])
            ->assertJsonValidationErrors(['schedules.3.closed_weeks.0']);
    }
}
