<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 院長のログイン。入力チェックと、パスワードの総当たりを防ぐ試行回数の制限を行う。
 */
class LoginRequest extends FormRequest
{
    // 同じメールアドレス＋同じ IP アドレスで、この回数失敗したらログインを制限する
    public const MAX_ATTEMPTS = 5;

    // 失敗を数える期間（秒）。この期間に MAX_ATTEMPTS 回失敗すると、期間が終わるまでログインできない
    public const LOCKOUT_SECONDS = 600;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * メールアドレスとパスワードを確かめて、ログインする
     *
     * @throws ValidationException 失敗したとき（422）、または制限中のとき（429）
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // パスワードは DB のハッシュ値と照合される（平文では比べない）
        if (! Auth::guard('web')->attempt($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);

            // どちらが違うかは教えない（「メールアドレスは合っている」と分かると攻撃の手がかりになる）
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $minutes = (int) ceil(RateLimiter::availableIn($this->throttleKey()) / 60);

        throw ValidationException::withMessages([
            'email' => "ログインに続けて失敗したため、一時的にログインを制限しています。{$minutes}分後にもう一度お試しください。",
        ])->status(429);
    }

    /**
     * 失敗の回数を数える単位（メールアドレス＋IP アドレス）
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
