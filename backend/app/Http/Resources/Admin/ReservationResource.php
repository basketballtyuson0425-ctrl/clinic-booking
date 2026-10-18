<?php

namespace App\Http\Resources\Admin;

use App\Models\Reservation;
use App\Services\HoursImpact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理画面に返す予約の形。院長は患者に連絡するので、電話番号・メールアドレスは伏せずに返す。
 * 鍵のハッシュ値（manage_token_hash）などの内部の値は返さない
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_at' => $this->start_at->format('Y-m-d H:i'),
            'end_at' => $this->end_at->format('Y-m-d H:i'),
            'status' => $this->status->value,
            // 「来院済み」は状態として持たず、終了時刻を過ぎたかで判定する
            'is_past' => $this->end_at->isPast(),
            'patient_name' => $this->patient_name,
            'patient_phone' => $this->patient_phone,
            'patient_email' => $this->patient_email,
            'visit_type' => $this->visit_type->value,
            'treatment_request' => $this->treatment_request,
            'source' => $this->source->value,
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i'),
            'cancelled_by' => $this->cancelled_by?->value,
            // 予約管理 URL を発行済みか（再送すると、以前の URL が使えなくなることを画面で伝えるため）
            'has_manage_url' => $this->manage_token_hash !== null,
            // 要連絡（営業時間の変更で、今の営業時間から外れた予約）。一覧では、まとめて判定した結果を使う
            'needs_contact' => $this->resource->needsContact
                ?? in_array($this->id, app(HoursImpact::class)->needsContactIds(collect([$this->resource])), true),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
            'last_mail' => $this->whenLoaded('latestMailLog', fn () => new MailLogResource($this->latestMailLog)),
            'mail_logs' => MailLogResource::collection($this->whenLoaded('mailLogs')),
        ];
    }
}
