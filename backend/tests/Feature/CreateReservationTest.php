<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Database\Seeders\WeeklyScheduleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 患者のネット予約（POST /api/reservations）を確かめる
 */
class CreateReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WeeklyScheduleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00')); // 木曜
    }

    /**
     * 正しい入力の見本。一部だけ変えたいときは $overrides で上書きする
     *
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

    // ---- 予約できる場合 ----

    public function test_正しい内容なら予約できる(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('start_at', '2026-11-06 10:00')
            ->assertJsonPath('patient_name', '山田 太郎')
            ->assertJsonPath('has_email', true);

        $reservation = Reservation::sole();
        $this->assertSame(ReservationStatus::Reserved, $reservation->status);
        $this->assertSame(ReservationSource::Web, $reservation->source);
        $this->assertSame('2026-11-06 10:30:00', $reservation->end_at->format('Y-m-d H:i:s'));
    }

    public function test_予約管理の鍵はハッシュ値だけを保存する(): void
    {
        $token = $this->postJson('/api/reservations', $this->payload())->json('manage_token');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);

        $reservation = Reservation::sole();
        $this->assertNotSame($token, $reservation->manage_token_hash);
        $this->assertSame(hash('sha256', $token), $reservation->manage_token_hash);
    }

    public function test_メールアドレスなしでも予約できる(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload([
            'patient_email' => '',
            'patient_email_confirmation' => '',
        ]));

        $response->assertCreated()->assertJsonPath('has_email', false);
        $this->assertNull(Reservation::sole()->patient_email);
    }

    public function test_全角の電話番号は半角にそろえて保存する(): void
    {
        $this->postJson('/api/reservations', $this->payload(['patient_phone' => '０９０－１２３４－５６７８']))
            ->assertCreated();

        $this->assertSame('090-1234-5678', Reservation::sole()->patient_phone);
    }

    // ---- 入力の形がおかしい場合（422） ----

    public function test_必須項目がないと日本語のエラーを返す(): void
    {
        $response = $this->postJson('/api/reservations', []);

        $response->assertUnprocessable()
            ->assertJsonPath('message', '氏名を入力してください。 （ほか3件）')
            ->assertJsonValidationErrors([
                'patient_name' => '氏名を入力してください。',
                'patient_phone' => '電話番号を入力してください。',
                'visit_type' => '初回か再来かを選択してください。',
                'start_at' => '予約日時を選択してください。',
            ]);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_メールアドレスの形式が違うとエラー(): void
    {
        $this->postJson('/api/reservations', $this->payload([
            'patient_email' => 'taro-example.com',
            'patient_email_confirmation' => 'taro-example.com',
        ]))->assertJsonValidationErrors(['patient_email' => 'メールアドレスの形式が正しくありません。']);
    }

    public function test_確認用メールアドレスが一致しないとエラー(): void
    {
        $this->postJson('/api/reservations', $this->payload([
            'patient_email_confirmation' => 'tar0@example.com',
        ]))->assertJsonValidationErrors(['patient_email' => 'メールアドレスが確認用と一致しません。']);
    }

    public function test_電話番号の形がおかしいとエラー(): void
    {
        foreach (['abc-defg-hijk', '1234567890', '090-1234', '090-1234-56789'] as $phone) {
            $this->postJson('/api/reservations', $this->payload(['patient_phone' => $phone]))
                ->assertJsonValidationErrors('patient_phone');
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_初回再来と施術希望内容と日時の形をチェックする(): void
    {
        $this->postJson('/api/reservations', $this->payload([
            'visit_type' => 'other',
            'treatment_request' => str_repeat('あ', 501),
            'start_at' => '2026/11/06 10:00',
        ]))->assertJsonValidationErrors([
            'visit_type' => '初回／再来の選択が正しくありません。',
            'treatment_request' => '施術希望内容は500文字以内で入力してください。',
            'start_at' => '予約日時の形式が正しくありません。',
        ]);
    }

    // ---- 予約できない日時（422） ----

    public function test_営業時間外の日時は予約できない(): void
    {
        $outsideHours = [
            '2026-11-08 10:00', // 日曜
            '2026-11-06 13:00', // 前半枠の終了時刻ちょうど
            '2026-11-06 09:15', // 30分刻みではない
            '2026-11-23 10:00', // 祝日（勤労感謝の日）
        ];

        foreach ($outsideHours as $startAt) {
            $this->postJson('/api/reservations', $this->payload(['start_at' => $startAt]))
                ->assertJsonValidationErrors(['start_at' => '選択した日時は予約できません。別の日時を選んでください。']);
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_予約可能期間の外は予約できない(): void
    {
        $this->postJson('/api/reservations', $this->payload(['start_at' => '2026-12-07 10:00'])) // 31日後より先
            ->assertJsonValidationErrors('start_at');
        $this->postJson('/api/reservations', $this->payload(['start_at' => '2026-11-04 10:00'])) // 昨日
            ->assertJsonValidationErrors('start_at');
    }

    public function test_予約時刻の1時間前を過ぎると予約できない(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:30:00'));

        $this->postJson('/api/reservations', $this->payload(['start_at' => '2026-11-06 10:00']))
            ->assertJsonValidationErrors('start_at');
        $this->postJson('/api/reservations', $this->payload(['start_at' => '2026-11-06 10:30']))
            ->assertCreated();
    }

    // ---- 他の予約とぶつかる場合（409） ----

    public function test_埋まっている枠は予約できない(): void
    {
        Reservation::factory()->create(['start_at' => '2026-11-06 10:00:00']);

        $this->postJson('/api/reservations', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'この時間はすでに予約が入りました。別の時間を選んでください。');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_キャンセル済みの枠は予約できる(): void
    {
        $cancelled = Reservation::factory()->create(['start_at' => '2026-11-06 10:00:00']);
        $cancelled->cancel(CancelledBy::Patient);

        $this->postJson('/api/reservations', $this->payload())->assertCreated();
    }

    public function test_同じ人は表記ゆれがあっても同じ日に2件予約できない(): void
    {
        $this->postJson('/api/reservations', $this->payload())->assertCreated();

        $this->postJson('/api/reservations', $this->payload([
            'patient_name' => '山田太郎',
            'patient_phone' => '09012345678',
            'start_at' => '2026-11-06 16:00',
        ]))
            ->assertStatus(409)
            ->assertJsonPath('message', '同じ日にすでにご予約があります。ご予約の変更は、予約管理ページまたはお電話でお願いします。');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_同じ人でも別の日なら予約できる(): void
    {
        $this->postJson('/api/reservations', $this->payload())->assertCreated();
        $this->postJson('/api/reservations', $this->payload(['start_at' => '2026-11-09 10:00']))->assertCreated();

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_確認の直後に別の予約が割り込んでも二重予約にならない(): void
    {
        // 「事前確認で reservations テーブルを調べた直後」に、別の人の予約が先に確定した状況を作る
        $interrupted = false;
        DB::listen(function (QueryExecuted $query) use (&$interrupted) {
            if ($interrupted || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, '"reservations"')) {
                return;
            }
            $interrupted = true;
            Reservation::factory()->create([
                'patient_name' => '割込 次郎',
                'patient_phone' => '080-0000-0000',
                'start_at' => '2026-11-06 10:00:00',
            ]);
        });

        $this->postJson('/api/reservations', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'この時間はすでに予約が入りました。別の時間を選んでください。');

        // 先に確定した1件だけが残る
        $this->assertTrue($interrupted);
        $this->assertSame('割込 次郎', Reservation::sole()->patient_name);
    }

    // ---- アクセス回数の制限 ----

    public function test_1分に11回目の予約登録は制限される(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/reservations', [])->assertUnprocessable();
        }

        $this->postJson('/api/reservations', [])->assertTooManyRequests();
    }
}
