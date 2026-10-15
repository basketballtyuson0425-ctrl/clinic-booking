<?php

namespace Tests\Unit;

use App\Models\Reservation;
use PHPUnit\Framework\TestCase;

/**
 * 同一人物の判定に使う「表記ゆれをそろえる処理」を確かめる
 */
class ReservationNormalizeTest extends TestCase
{
    public function test_氏名の空白は半角も全角も取り除く(): void
    {
        $this->assertSame('山田太郎', Reservation::normalizeName('山田 太郎'));
        $this->assertSame('山田太郎', Reservation::normalizeName('山田　太郎'));
        $this->assertSame('山田太郎', Reservation::normalizeName(' 山田太郎 '));
    }

    public function test_氏名の半角カナは全角カナにそろえる(): void
    {
        $this->assertSame('ヤマダタロウ', Reservation::normalizeName('ﾔﾏﾀﾞ ﾀﾛｳ'));
    }

    public function test_氏名の全角英字は半角の小文字にそろえる(): void
    {
        $this->assertSame('johnsmith', Reservation::normalizeName('Ｊｏｈｎ Ｓｍｉｔｈ'));
    }

    public function test_電話番号は数字だけにする(): void
    {
        $this->assertSame('09012345678', Reservation::normalizePhone('090-1234-5678'));
        $this->assertSame('09012345678', Reservation::normalizePhone('０９０－１２３４－５６７８'));
        $this->assertSame('0312345678', Reservation::normalizePhone('(03) 1234 5678'));
    }
}
