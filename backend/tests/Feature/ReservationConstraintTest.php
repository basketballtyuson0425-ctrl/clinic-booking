<?php

namespace Tests\Feature;

use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 二重予約・同日重複予約を DB の一意制約で防げているかを確かめる
 */
class ReservationConstraintTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slot = Carbon::parse('2026-11-02 10:00:00');
    }

    public function test_保存するとend_atとactive列が自動で設定される(): void
    {
        $reservation = Reservation::factory()->create(['start_at' => $this->slot]);

        $this->assertSame(ReservationStatus::Reserved, $reservation->status);
        $this->assertSame('2026-11-02 10:00:00', $reservation->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 10:30:00', $reservation->end_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 10:00:00', $reservation->active_start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02', $reservation->active_date);
    }

    public function test_キャンセルするとactive列が空になり記録は残る(): void
    {
        $reservation = Reservation::factory()->create(['start_at' => $this->slot]);

        $reservation->cancel(CancelledBy::Patient);
        $reservation->refresh();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(CancelledBy::Patient, $reservation->cancelled_by);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertNull($reservation->active_start_at);
        $this->assertNull($reservation->active_date);
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_同じ枠に予約中の予約を2件入れるとデータベースが拒否する(): void
    {
        Reservation::factory()->create(['start_at' => $this->slot]);

        $this->expectException(UniqueConstraintViolationException::class);

        Reservation::factory()->create(['start_at' => $this->slot]);
    }

    public function test_キャンセル済みの枠にはもう一度予約できる(): void
    {
        $first = Reservation::factory()->create(['start_at' => $this->slot]);
        $first->cancel(CancelledBy::Patient);

        Reservation::factory()->create(['start_at' => $this->slot]);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_同じ人は表記ゆれがあっても同じ日に2件予約できない(): void
    {
        Reservation::factory()->create([
            'patient_name' => '山田 太郎',
            'patient_phone' => '090-1234-5678',
            'start_at' => $this->slot,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        // 空白なし・ハイフンなしで、同じ日の別の時間を予約しようとする
        Reservation::factory()->create([
            'patient_name' => '山田太郎',
            'patient_phone' => '09012345678',
            'start_at' => $this->slot->copy()->setTime(16, 0),
        ]);
    }

    public function test_同じ人でも別の日なら予約できる(): void
    {
        $person = ['patient_name' => '山田 太郎', 'patient_phone' => '090-1234-5678'];

        Reservation::factory()->create([...$person, 'start_at' => $this->slot]);
        Reservation::factory()->create([...$person, 'start_at' => $this->slot->copy()->addDay()]);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_同じ人でもキャンセル済みなら同じ日にもう一度予約できる(): void
    {
        $person = ['patient_name' => '山田 太郎', 'patient_phone' => '090-1234-5678'];

        $first = Reservation::factory()->create([...$person, 'start_at' => $this->slot]);
        $first->cancel(CancelledBy::Patient);

        Reservation::factory()->create([...$person, 'start_at' => $this->slot->copy()->setTime(16, 0)]);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_名前が同じでも電話番号が違えば別の人として扱う(): void
    {
        Reservation::factory()->create([
            'patient_name' => '山田 太郎',
            'patient_phone' => '090-1234-5678',
            'start_at' => $this->slot,
        ]);
        Reservation::factory()->create([
            'patient_name' => '山田 太郎',
            'patient_phone' => '080-9999-0000',
            'start_at' => $this->slot->copy()->setTime(16, 0),
        ]);

        $this->assertDatabaseCount('reservations', 2);
    }
}
