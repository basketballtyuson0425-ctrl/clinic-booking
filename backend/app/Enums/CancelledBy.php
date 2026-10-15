<?php

namespace App\Enums;

// 誰がキャンセルしたか
enum CancelledBy: string
{
    case Patient = 'patient';
    case Admin = 'admin';
}
