<?php

namespace App\Enums;

// 予約の登録元（患者のネット予約／院長による登録）
enum ReservationSource: string
{
    case Web = 'web';
    case Admin = 'admin';
}
