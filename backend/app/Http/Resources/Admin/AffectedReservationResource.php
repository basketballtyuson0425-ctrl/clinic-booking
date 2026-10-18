<?php

namespace App\Http\Resources\Admin;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 営業時間の変更で影響を受ける予約（確認の画面で、院長が誰に連絡するか分かるようにする）
 *
 * @mixin Reservation
 */
class AffectedReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_at' => $this->start_at->format('Y-m-d H:i'),
            'end_at' => $this->end_at->format('Y-m-d H:i'),
            'patient_name' => $this->patient_name,
            'patient_phone' => $this->patient_phone,
            'has_email' => $this->patient_email !== null,
        ];
    }
}
