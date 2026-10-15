<?php

namespace App\Models;

use App\Enums\CancelledBy;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\VisitType;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 予約（reservations テーブル）
 *
 * status・name_key・phone_key・active_*・manage_token_hash は $fillable に含めない。
 * 外から渡された値で直接書き換えられないようにし、このクラスの処理だけで設定する。
 */
#[Fillable([
    'patient_name',
    'patient_phone',
    'patient_email',
    'visit_type',
    'treatment_request',
    'start_at',
    'source',
])]
#[Hidden(['manage_token_hash', 'name_key', 'phone_key', 'active_start_at', 'active_date'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    // 予約枠の長さ（分）
    public const SLOT_MINUTES = 30;

    // 新しく作った予約は「予約中」から始まる
    protected $attributes = [
        'status' => 'reserved',
    ];

    protected function casts(): array
    {
        return [
            'visit_type' => VisitType::class,
            'status' => ReservationStatus::class,
            'cancelled_by' => CancelledBy::class,
            'source' => ReservationSource::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'active_start_at' => 'datetime',
        ];
    }

    /**
     * 保存する直前に、毎回必ず次の列を計算し直す。
     * どこから保存しても同じ処理が通るので、キャンセルしたのに枠が埋まったまま、といった更新漏れが起きない。
     */
    protected static function booted(): void
    {
        static::saving(function (Reservation $reservation) {
            $reservation->end_at = $reservation->start_at->addMinutes(self::SLOT_MINUTES);
            $reservation->name_key = self::normalizeName($reservation->patient_name);
            $reservation->phone_key = self::normalizePhone($reservation->patient_phone);

            if ($reservation->status === ReservationStatus::Reserved) {
                // 予約中：枠と「その日のその人」を確保する（DB の一意制約の対象になる）
                $reservation->active_start_at = $reservation->start_at;
                $reservation->active_date = $reservation->start_at->toDateString();
            } else {
                // キャンセル済み：NULL にして枠を空ける
                $reservation->active_start_at = null;
                $reservation->active_date = null;
            }
        });
    }

    /**
     * 予約をキャンセルする（データは削除せず「キャンセル済み」として残す）。
     */
    public function cancel(CancelledBy $by): void
    {
        $this->status = ReservationStatus::Cancelled;
        $this->cancelled_at = now();
        $this->cancelled_by = $by;
        $this->save();
    }

    /**
     * 予約管理 URL 用の鍵（トークン）を新しく発行する。
     * DB にはハッシュ値だけを保存し、鍵そのものは戻り値としてだけ返す（呼び出し側で save() すること）。
     * 推測できないよう、暗号用の乱数 32 バイトから 64 文字の鍵を作る。
     */
    public function issueManageToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->manage_token_hash = self::hashToken($token);

        return $token;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * 同一人物の判定用に氏名の表記ゆれをそろえる。
     * 例：「山田 太郎」「山田　太郎」「山田太郎」→「山田太郎」
     */
    public static function normalizeName(string $name): string
    {
        // a: 全角英数字→半角、s: 全角スペース→半角、K: 半角カナ→全角カナ、V: 濁点を1文字にまとめる
        $name = mb_convert_kana($name, 'asKV');
        $name = mb_strtolower($name);

        return preg_replace('/\s+/u', '', $name);
    }

    /**
     * 同一人物の判定用に電話番号を数字だけにする。
     * 例：「090-1234-5678」「０９０１２３４５６７８」→「09012345678」
     */
    public static function normalizePhone(string $phone): string
    {
        $phone = mb_convert_kana($phone, 'n'); // 全角数字→半角

        return preg_replace('/[^0-9]/', '', $phone);
    }

    public function mailLogs(): HasMany
    {
        return $this->hasMany(MailLog::class);
    }
}
