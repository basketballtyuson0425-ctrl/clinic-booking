<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 院長のパスワード再設定コマンド（admin:password）を確かめる
 */
class ChangeAdminPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    private function addSession(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_新しいパスワードを設定しハッシュ化して保存する(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1234']);

        $this->artisan('admin:password')
            ->expectsQuestion('新しいパスワード（12文字以上）', 'new-password-5678')
            ->expectsQuestion('新しいパスワード（確認のためもう一度）', 'new-password-5678')
            ->assertSuccessful();

        $password = $user->fresh()->password;
        $this->assertNotSame('new-password-5678', $password);
        $this->assertTrue(Hash::check('new-password-5678', $password));
        $this->assertFalse(Hash::check('old-password-1234', $password));
    }

    public function test_院長のログイン中のセッションをすべて削除する(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $this->addSession('director-pc', $user->id);
        $this->addSession('director-phone', $user->id);
        $this->addSession('patient-browser', null); // ログインしていない人のセッションは残す

        $this->artisan('admin:password')
            ->expectsQuestion('新しいパスワード（12文字以上）', 'new-password-5678')
            ->expectsQuestion('新しいパスワード（確認のためもう一度）', 'new-password-5678')
            ->assertSuccessful();

        $this->assertSame(['patient-browser'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_院長アカウントがなければ失敗する(): void
    {
        $this->artisan('admin:password')->assertFailed();
    }

    public function test_パスワードが12文字未満なら変更しない(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1234']);

        $this->artisan('admin:password')
            ->expectsQuestion('新しいパスワード（12文字以上）', 'short-pass')
            ->expectsQuestion('新しいパスワード（確認のためもう一度）', 'short-pass')
            ->assertFailed();

        $this->assertTrue(Hash::check('old-password-1234', $user->fresh()->password));
    }

    public function test_確認用パスワードが一致しなければ変更しない(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'old-password-1234']);
        $this->addSession('director-pc', $user->id);

        $this->artisan('admin:password')
            ->expectsQuestion('新しいパスワード（12文字以上）', 'new-password-5678')
            ->expectsQuestion('新しいパスワード（確認のためもう一度）', 'new-password-5679')
            ->assertFailed();

        $this->assertTrue(Hash::check('old-password-1234', $user->fresh()->password));
        $this->assertSame(1, DB::table('sessions')->count()); // 失敗したときはログインも切らない
    }
}
