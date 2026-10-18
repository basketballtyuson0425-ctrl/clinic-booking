<?php

namespace App\Http\Resources\Admin;

use App\Models\MailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理画面に返すメールの送信記録の形
 *
 * @mixin MailLog
 */
class MailLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'to_email' => $this->to_email,
            'error_message' => $this->error_message,
            // 送信を試した日時（失敗したときは sent_at が空なので、記録した日時を使う）
            'created_at' => $this->created_at->format('Y-m-d H:i'),
            // メール送信記録の画面で、どの予約のメールかを表示する
            'reservation' => $this->whenLoaded('reservation', fn () => [
                'id' => $this->reservation->id,
                'start_at' => $this->reservation->start_at->format('Y-m-d H:i'),
                'patient_name' => $this->reservation->patient_name,
            ]),
        ];
    }
}
