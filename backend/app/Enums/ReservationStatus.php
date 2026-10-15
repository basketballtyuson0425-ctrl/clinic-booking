<?php

namespace App\Enums;

// 予約の状態。「来院済み」は状態として持たず、end_at が過ぎたかどうかで判定する
enum ReservationStatus: string
{
    case Reserved = 'reserved';
    case Cancelled = 'cancelled';
}
