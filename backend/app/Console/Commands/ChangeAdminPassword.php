<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * 院長のパスワードを再設定する。
 * 管理画面にはパスワード再設定の機能を作らない（メールを乗っ取られたときの入口を増やさない）ため、
 * 忘れたときはサーバー上でこのコマンドを実行する。
 */
#[Signature('admin:password')]
#[Description('院長のパスワードを再設定し、ログイン中の端末をすべてログアウトさせます')]
class ChangeAdminPassword extends Command
{
    public function handle(): int
    {
        $user = User::first();

        if ($user === null) {
            $this->error('院長アカウントがありません。先に php artisan admin:create を実行してください。');

            return self::FAILURE;
        }

        $input = [
            // secret() は入力した文字を画面に表示しない
            'password' => $this->secret('新しいパスワード（12文字以上）'),
            'password_confirmation' => $this->secret('新しいパスワード（確認のためもう一度）'),
        ];

        $validator = Validator::make($input, [
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // パスワードは User モデルの 'hashed' キャストにより、保存時に自動でハッシュ化される
        $user->update(['password' => $input['password']]);

        // パスワードが盗まれた可能性もあるので、ログイン中の端末をすべてログアウトさせる。
        // セッションを DB に保存している場合は、その場で削除する。
        // （ほかの保存方法でも、Sanctum の AuthenticateSession が「パスワードが変わった」ことに気づき、
        //   次の操作のときにログアウトさせる）
        if (config('session.driver') === 'database') {
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
        }

        $this->info('パスワードを変更しました。ログイン中の端末はすべてログアウトしました。');

        return self::SUCCESS;
    }
}
