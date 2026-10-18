<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * 予約がある日の営業時間を、院長の確認なしに変えようとしたことを表す（409 Conflict）。
 * 画面の確認を飛ばして保存されないよう、サーバー側でも確かめる（要件25）
 */
class HoursChangeNeedsConfirmationException extends Exception
{
    /**
     * @param  array<string, mixed>  $impact  影響の内容（予約の件数・影響のある予約・変更後の営業時間）
     */
    public function __construct(public readonly array $impact)
    {
        parent::__construct('予約が入っているため、変更の前に影響を確認してください。');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'reason' => 'confirmation_required',
            'impact' => $this->impact,
        ], 409);
    }

    /**
     * 想定内の出来事なので、エラーログには記録しない
     */
    public function report(): void {}
}
