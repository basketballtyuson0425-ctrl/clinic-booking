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
    public static function slotTaken(): self
    {
        return new self('この時間はすでに予約が入りました。別の時間を選んでください。');
    }

    public static function sameDay(): self
    {
        return new self('同じ日にすでにご予約があります。ご予約の変更は、予約管理ページまたはお電話でお願いします。');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }

    /**
     * よくある想定内の出来事なので、エラーログには記録しない
     */
    public function report(): void {}
}
