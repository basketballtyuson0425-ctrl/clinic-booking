<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * 他の予約とぶつかったため、予約できなかったことを表す。
 * Laravel がこのエラーを受け取ると、render() の内容（409 Conflict）を自動で返す。
 */
class ReservationConflictException extends Exception
{
    /**
     * @param  string  $reason  画面側で案内を切り替えるための目印（slot_taken：枠が埋まった／same_day：同じ人が同じ日に予約済み）
     */
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function slotTaken(): self
    {
        return new self('この時間はすでに予約が入りました。別の時間を選んでください。', 'slot_taken');
    }

    public static function sameDay(): self
    {
        return new self('同じ日にすでにご予約があります。ご予約の変更は、予約管理ページまたはお電話でお願いします。', 'same_day');
    }

    /**
     * 院長の操作のとき（患者向けの「予約管理ページまたはお電話で」の案内を含めない）
     */
    public static function sameDayForAdmin(): self
    {
        return new self('同じ日に、同じ方（氏名と電話番号が同じ）の予約がすでにあります。', 'same_day');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'reason' => $this->reason], 409);
    }

    /**
     * よくある想定内の出来事なので、エラーログには記録しない
     */
    public function report(): void {}
}
