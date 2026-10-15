<?php

namespace App\Enums;

// 初回／再来
enum VisitType: string
{
    case First = 'first';
    case Return = 'return';
}
