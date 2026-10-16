<?php

namespace App\Enums;

// 初回／再来
enum VisitType: string
{
    case First = 'first';
    case Return = 'return';

    /**
     * 患者向けの表示（メールなど）
     */
    public function label(): string
    {
        return match ($this) {
            self::First => '初めて',
            self::Return => '2回目以降',
        };
    }
}
