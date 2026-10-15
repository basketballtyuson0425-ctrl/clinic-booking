<?php

namespace App\Services;

use App\Enums\ReservationSource;
use App\Exceptions\ReservationConflictException;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 予約を確定する。
 *
 * 二重予約・同日重複予約の防止は二段構え：
 * 1. 保存の前にアプリで確認し、分かりやすいメッセージを返す
 * 2. 確認と保存のわずかな間に他の予約が割り込んでも、DB の一意制約が後の予約を拒否する（最終的な防御）
 */
class ReservationBooking
{
    public function __construct(private Availability $availability) {}

    /**
     * 患者のネット予約を作る。
     *
     * @param  array<string, mixed>  $data  StoreReservationRequest でチェック済みの入力
     * @return array{0: Reservation, 1: string} 作成した予約と、予約管理 URL の鍵
     *
     * @throws ValidationException 患者が予約できない日時のとき（422）
     * @throws ReservationConflictException 他の予約とぶつかったとき（409）
     */
    public function createForPatient(array $data): array
    {
        $startAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['start_at'])->startOfMinute();

        // 営業時間内の30分枠・予約可能期間内・締め切り前か（○／× の画面と同じルール）
        if (! $this->availability->isBookableSlot($startAt)) {
            throw ValidationException::withMessages([
                'start_at' => '選択した日時は予約できません。別の日時を選んでください。',
            ]);
        }

        $reservation = new Reservation([
            ...$data,
            'start_at' => $startAt,
            'source' => ReservationSource::Web,
        ]);
        $token = $reservation->issueManageToken();

        $this->save($reservation);

        return [$reservation, $token];
    }

    private function save(Reservation $reservation): void
    {
        $this->ensureNoConflict($reservation);

        try {
            DB::transaction(fn () => $reservation->save());
        } catch (UniqueConstraintViolationException) {
            // 確認のあと、ほぼ同時に別の予約が先に確定した。どちらとぶつかったかを調べ直して伝える
            $this->ensureNoConflict($reservation);

            throw ReservationConflictException::slotTaken();
        }
    }

    /**
     * 同じ枠の予約、または同じ人の同じ日の予約（いずれも予約中のもの）があれば例外を投げる
     */
    private function ensureNoConflict(Reservation $reservation): void
    {
        // 予約を変更するとき（ステップ6・8）は、自分自身とはぶつからないようにする
        $others = fn () => Reservation::query()
            ->when($reservation->exists, fn ($query) => $query->whereKeyNot($reservation->getKey()));

        if ($others()->where('active_start_at', $reservation->start_at)->exists()) {
            throw ReservationConflictException::slotTaken();
        }

        $samePersonSameDay = $others()
            ->where('active_date', $reservation->start_at->toDateString())
            ->where('name_key', Reservation::normalizeName($reservation->patient_name))
            ->where('phone_key', Reservation::normalizePhone($reservation->patient_phone))
            ->exists();

        if ($samePersonSameDay) {
            throw ReservationConflictException::sameDay();
        }
    }
}
