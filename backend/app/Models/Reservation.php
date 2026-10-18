<?php

namespace App\Models;

use App\Enums\CancelledBy;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\VisitType;
use App\Services\Availability;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /**
     * 「要連絡」（今の営業時間から外れている）か。DB には保存しない値で、管理画面の一覧で
     * HoursImpact がまとめて判定した結果を入れておく（null なら、まだ判定していない）
     */
    public ?bool $needsContact = null;

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
     * 予約管理 URL の鍵から予約を探す。予約の終了時刻を過ぎたものは見つからない扱いにする（URL の無効化）
     */
    public static function findByManageToken(string $token): ?self
    {
        return self::query()
            ->where('manage_token_hash', self::hashToken($token))
            ->where('end_at', '>', now())
            ->first();
    }

    /**
     * 患者が予約管理ページから変更・キャンセルできない理由。できる場合は null
     *
     * @return 'cancelled'|'no_email'|'deadline_passed'|null
     */
    public function patientCannotModifyReason(): ?string
    {
        if ($this->status === ReservationStatus::Cancelled) {
            return 'cancelled';
        }
        // メールアドレスがない予約は確認のみ。変更・キャンセルは電話で受け付ける
        if ($this->patient_email === null) {
            return 'no_email';
        }
        if (! Availability::isBeforeDeadline($this->start_at)) {
            return 'deadline_passed';
        }

        return null;
    }

    /**
     * 例：2026年11月6日（金）10:00〜10:30
     */
    public function dateTimeLabel(): string
    {
        return $this->start_at->locale('ja')->isoFormat('YYYY年M月D日（ddd）H:mm').'〜'.$this->end_at->format('H:i');
    }

    /**
     * 電話番号の一部を伏せる（最初の3桁と最後の4桁だけ見せる）。例：090-1234-5678 → 090-****-5678
     */
    public function maskedPhone(): string
    {
        $digitCount = strlen(self::normalizePhone($this->patient_phone));
        $digitIndex = 0;
        $masked = '';

        foreach (str_split($this->patient_phone) as $char) {
            if (ctype_digit($char)) {
                $masked .= ($digitIndex >= 3 && $digitIndex < $digitCount - 4) ? '*' : $char;
                $digitIndex++;
            } else {
                $masked .= $char;
            }
        }

        return $masked;
    }

    /**
     * メールアドレスの一部を伏せる（@ の前は最初の2文字だけ見せる）。例：hanako@example.com → ha****@example.com
     */
    public function maskedEmail(): ?string
    {
        if ($this->patient_email === null) {
            return null;
        }

        [$local, $domain] = explode('@', $this->patient_email, 2);

        return mb_substr($local, 0, min(2, mb_strlen($local) - 1)).'****@'.$domain;
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

    /**
     * 最後に送ったメール（管理画面の一覧で、送信に失敗していないかを表示するため）
     */
    public function latestMailLog(): HasOne
    {
        return $this->hasOne(MailLog::class)->latestOfMany();
    }
}
