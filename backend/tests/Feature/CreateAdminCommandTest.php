<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_院長アカウントを作成しパスワードはハッシュ化して保存する(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('名前', '院長')
            ->expectsQuestion('メールアドレス', 'director@example.com')
            ->expectsQuestion('パスワード（12文字以上）', 'correct-horse-battery')
            ->expectsQuestion('パスワード（確認のためもう一度）', 'correct-horse-battery')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'director@example.com');
        $this->assertNotNull($user);
        // 平文のまま保存されていないこと、正しいパスワードで照合できること
        $this->assertNotSame('correct-horse-battery', $user->password);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_すでにアカウントがある場合は2件目を作らない(): void
    {
        User::factory()->create();

        $this->artisan('admin:create')->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_パスワードが12文字未満なら作成しない(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('名前', '院長')
            ->expectsQuestion('メールアドレス', 'director@example.com')
            ->expectsQuestion('パスワード（12文字以上）', 'short-pass')
            ->expectsQuestion('パスワード（確認のためもう一度）', 'short-pass')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_確認用パスワードが一致しなければ作成しない(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('名前', '院長')
            ->expectsQuestion('メールアドレス', 'director@example.com')
            ->expectsQuestion('パスワード（12文字以上）', 'correct-horse-battery')
            ->expectsQuestion('パスワード（確認のためもう一度）', 'correct-horse-batteri')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
