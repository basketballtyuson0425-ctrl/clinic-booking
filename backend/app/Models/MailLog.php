<?php

namespace App\Models;

use App\Enums\MailStatus;
use App\Enums\MailType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * メールの送信結果（mail_logs テーブル）
 */
#[Fillable(['reservation_id', 'type', 'to_email', 'status', 'error_message', 'sent_at'])]
class MailLog extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MailType::class,
            'status' => MailStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
