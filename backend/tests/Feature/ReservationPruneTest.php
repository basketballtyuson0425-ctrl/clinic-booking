<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\MailStatus;
use App\Enums\MailType;
use App\Models\MailLog;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * 終了から1年を過ぎた予約の自動削除（model:prune）を確かめる
 */
class ReservationPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:30:00'));
    }

    /**
     * 終了時刻を指定して予約を作る（開始時刻は終了時刻の30分前）
     *
     * @param  array<string, mixed>  $attributes
     */
    private function reservationEndingAt(string $endAt, array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'start_at' => CarbonImmutable::parse($endAt)->subMinutes(Reservation::SLOT_MINUTES),
            ...$attributes,
        ]);
    }

    public function test_終了から1年を過ぎた予約は削除され1年以内の予約は残る(): void
    {
        $old = $this->reservationEndingAt('2025-10-01 10:30:00');
        $recent = $this->reservationEndingAt('2026-01-15 10:30:00');
        $future = $this->reservationEndingAt('2026-11-06 10:30:00');

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($future);
    }

    public function test_ちょうど1年前に終了した予約は残りそれより1秒前の予約は削除される(): void
    {
        // 今は 2026-11-05 08:30:00 なので、ちょうど1年前は 2025-11-05 08:30:00
        $exactlyOneYear = $this->reservationEndingAt('2025-11-05 08:30:00', ['patient_phone' => '090-0000-0001']);
        $oneSecondBefore = $this->reservationEndingAt('2025-11-05 08:29:59', ['patient_phone' => '090-0000-0002']);

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertModelExists($exactlyOneYear);
        $this->assertModelMissing($oneSecondBefore);
    }

    public function test_キャンセル済みの予約も同じ条件で削除される(): void
    {
        $oldCancelled = $this->reservationEndingAt('2025-10-01 10:30:00');
        $oldCancelled->cancel(CancelledBy::Patient);
        $recentCancelled = $this->reservationEndingAt('2026-01-15 10:30:00');
        $recentCancelled->cancel(CancelledBy::Admin);

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertModelMissing($oldCancelled);
        $this->assertModelExists($recentCancelled);
    }

    public function test_予約を削除するとメールの送信記録も一緒に消える(): void
    {
        $old = $this->reservationEndingAt('2025-10-01 10:30:00');
        $recent = $this->reservationEndingAt('2026-01-15 10:30:00');
        $oldLog = MailLog::create(['reservation_id' => $old->id, 'type' => MailType::Reserved, 'to_email' => 'old@example.com', 'status' => MailStatus::Sent, 'sent_at' => now()]);
        $recentLog = MailLog::create(['reservation_id' => $recent->id, 'type' => MailType::Reserved, 'to_email' => 'recent@example.com', 'status' => MailStatus::Sent, 'sent_at' => now()]);

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertModelMissing($oldLog);
        $this->assertModelExists($recentLog);
    }

    public function test_削除した件数だけをログに書き個人情報は書かない(): void
    {
        Log::spy(); // ログに書かれた内容を記録して、あとで確かめる
        $old = $this->reservationEndingAt('2025-10-01 10:30:00', ['patient_name' => '架空 花子', 'patient_phone' => '090-9999-8888']);
        $this->reservationEndingAt('2025-09-01 10:30:00');

        $this->artisan('model:prune')->assertSuccessful();

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($old) {
            $written = $message.json_encode($context, JSON_UNESCAPED_UNICODE);

            return $context === ['model' => Reservation::class, 'count' => 2]
                && ! str_contains($written, $old->patient_name)
                && ! str_contains($written, $old->patient_phone);
        });
    }

    public function test_削除するものがなければ何も消さずログも書かない(): void
    {
        Log::spy();
        $recent = $this->reservationEndingAt('2026-01-15 10:30:00');

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertModelExists($recent);
        Log::shouldNotHaveReceived('info');
    }

    public function test_自動削除が毎日3時に実行されるよう登録されている(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains($event->command ?? '', 'model:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 3 * * *', $events->first()->expression);
    }
}
