<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * 予約を変更・キャンセルできない状態であることを表す（403 Forbidden）。
 * 患者の予約管理ページと、院長の管理画面で使う（文章はそれぞれ向けのもの）。
 */
class ReservationNotModifiableException extends Exception
{
    private const MESSAGES = [
        'cancelled' => 'この予約はキャンセル済みのため、変更・キャンセルはできません。',
        'no_email' => 'メールアドレスのご登録がないため、オンラインでの変更・キャンセルはできません。お電話でお願いします。',
        'deadline_passed' => '予約時刻の1時間前を過ぎたため、オンラインでの変更・キャンセルはできません。お電話でお願いします。',
    ];

    private const ADMIN_MESSAGES = [
        'cancelled' => 'この予約はキャンセル済みのため、変更・キャンセル・メールの再送はできません。',
        'past' => '予約時刻を過ぎた予約は、日時の変更・キャンセル・メールの再送はできません。',
        'no_email' => 'メールアドレスが登録されていないため、メールを送れません。',
    ];

    /**
     * @param  string  $reason  画面で案内を切り替えるための目印（cancelled / no_email / deadline_passed / past）
     */
    public function __construct(public readonly string $reason, ?string $message = null)
    {
        parent::__construct($message ?? self::MESSAGES[$reason]);
    }

    /**
     * 院長の操作のとき（「お電話で」などの患者向けの案内を含めない）
     *
     * @param  'cancelled'|'past'|'no_email'  $reason
     */
    public static function forAdmin(string $reason): self
    {
        return new self($reason, self::ADMIN_MESSAGES[$reason]);
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
