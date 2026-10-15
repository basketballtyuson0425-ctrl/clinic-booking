<?php

namespace App\Enums;

// 送信したメールの種類
enum MailType: string
{
    case Reserved = 'reserved';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
    case HoursChanged = 'hours_changed';
}
