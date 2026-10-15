<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * 院長のログイン用アカウントを1件だけ作る。
 * 管理画面から誰でもアカウントを作れないよう、新規登録 API は用意せず、サーバー上でこのコマンドを実行して作る。
 */
#[Signature('admin:create')]
#[Description('院長のログイン用アカウントを作成します')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        // 利用者は院長1名だけなので、2件目は作らない
        if (User::exists()) {
            $this->error('院長アカウントはすでに作成されています。');

            return self::FAILURE;
        }

        $input = [
            'name' => $this->ask('名前'),
            'email' => $this->ask('メールアドレス'),
            // secret() は入力した文字を画面に表示しない
            'password' => $this->secret('パスワード（12文字以上）'),
            'password_confirmation' => $this->secret('パスワード（確認のためもう一度）'),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // パスワードは User モデルの 'hashed' キャストにより、保存時に自動でハッシュ化される
        User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $this->info('院長アカウントを作成しました。');

        return self::SUCCESS;
    }
}
