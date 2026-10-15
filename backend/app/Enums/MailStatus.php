<?php

namespace App\Enums;

// メールの送信結果
enum MailStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
