<?php

namespace App\Enums;

// 送信したメールの種類
enum MailType: string
{
    case Reserved = 'reserved';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
    case HoursChanged = 'hours_changed';
    case Resent = 'resent'; // 院長による「予約内容のお知らせ」の再送
}
