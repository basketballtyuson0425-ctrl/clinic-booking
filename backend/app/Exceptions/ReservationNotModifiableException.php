<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * 患者が予約管理ページから変更・キャンセルできない状態であることを表す（403 Forbidden）。
 */
class ReservationNotModifiableException extends Exception
{
    private const MESSAGES = [
        'cancelled' => 'この予約はキャンセル済みのため、変更・キャンセルはできません。',
        'no_email' => 'メールアドレスのご登録がないため、オンラインでの変更・キャンセルはできません。お電話でお願いします。',
        'deadline_passed' => '予約時刻の1時間前を過ぎたため、オンラインでの変更・キャンセルはできません。お電話でお願いします。',
    ];

    /**
     * @param  'cancelled'|'no_email'|'deadline_passed'  $reason  画面で案内を切り替えるための目印
     */
    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGES[$reason]);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'reason' => $this->reason], 403);
    }

    /**
     * 想定内の出来事なので、エラーログには記録しない
     */
    public function report(): void {}
}
