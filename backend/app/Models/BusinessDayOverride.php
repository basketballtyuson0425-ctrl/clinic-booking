<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 日付ごとの個別設定（business_day_overrides テーブル）
 *
 * date 列は "2026-10-10" の形の文字列で扱う。
 * Laravel の date キャストを使うと "2026-10-10 00:00:00" の形で保存され、
 * SQLite では where('date', '2026-10-10') で検索できなくなるため、あえてキャストしない。
 */
#[Fillable(['date', 'is_closed', 'am_start', 'am_end', 'pm_start', 'pm_end'])]
class BusinessDayOverride extends Model
{
    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
        ];
    }
}
