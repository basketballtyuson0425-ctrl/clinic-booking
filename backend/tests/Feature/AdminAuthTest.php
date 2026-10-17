<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 院長のログイン・ログアウト（/api/admin/...）を確かめる
 */
class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'name' => '院長',
            'email' => 'director@example.com',
            'password' => 'correct-horse-battery',
        ]);
        // 管理画面（SANCTUM_STATEFUL_DOMAINS に登録した画面）からのリクエストとして送る
        $this->withHeader('Referer', 'http://localhost:3000/admin/login');
    }

    private function login(string $password = 'correct-horse-battery', string $email = 'director@example.com')
    {
        return $this->postJson('/api/admin/login', ['email' => $email, 'password' => $password]);
    }

    public function test_正しいメールアドレスとパスワードでログインできる(): void
    {
        $this->login()
            ->assertOk()
            ->assertJsonPath('user', ['name' => '院長', 'email' => 'director@example.com']);

        $this->assertAuthenticatedAs($this->admin, 'web');
    }

    public function test_ログイン中の院長の情報を返しパスワードは返さない(): void
    {
        $this->login();

        $response = $this->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('user.name', '院長');

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString($this->admin->password, $response->getContent());
    }

    public function test_パスワードが違うとログインできない(): void
    {
        $this->login('wrong-password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);

        $this->assertGuest('web');
    }

    public function test_存在しないメールアドレスでも同じメッセージになる(): void
    {
        $this->login('correct-horse-battery', 'nobody@example.com')
            ->assertJsonValidationErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
    }

    public function test_入力が空ならエラー(): void
    {
        $this->postJson('/api/admin/login', [])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_5回失敗すると正しいパスワードでも10分間ログインできない(): void
    {
        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->login('wrong-password')->assertUnprocessable();
        }

        $this->login()
            ->assertStatus(429)
            ->assertJsonValidationErrors(['email' => 'ログインに続けて失敗したため、一時的にログインを制限しています。10分後にもう一度お試しください。']);
        $this->assertGuest('web');

        // 制限の期間が過ぎれば、ログインできる
        $this->travel(LoginRequest::LOCKOUT_SECONDS + 1)->seconds();
        $this->login()->assertOk();
    }

    public function test_別の接続元からは制限されない(): void
    {
        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->login('wrong-password');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->login()
            ->assertOk();
    }

    public function test_ログインに成功すると失敗の回数は0に戻る(): void
    {
        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS - 1; $i++) {
            $this->login('wrong-password');
        }
        $this->login()->assertOk();
        $this->postJson('/api/admin/logout')->assertNoContent();

        // また4回失敗しても、まだ制限されない
        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS - 1; $i++) {
            $this->login('wrong-password');
        }
        $this->login()->assertOk();
    }

    public function test_同じ接続元からのログインは1分に10回まで(): void
    {
        // メールアドレスを変えながら試す攻撃を想定する
        for ($i = 0; $i < 10; $i++) {
            $this->login('wrong-password', "user{$i}@example.com")->assertUnprocessable();
        }

        $this->login()->assertTooManyRequests();
    }

    public function test_ログアウトするとログインしていない状態に戻る(): void
    {
        $this->login();

        $this->postJson('/api/admin/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_ログインしていなければ院長の情報もログアウトも401(): void
    {
        $this->getJson('/api/admin/me')->assertUnauthorized();
        $this->postJson('/api/admin/logout')->assertUnauthorized();
    }

    public function test_管理画面以外からのログインは受け付けない(): void
    {
        // Referer（どのページから来たか）が付いていないリクエスト
        $this->flushHeaders();

        $this->login()
            ->assertForbidden()
            ->assertJsonPath('message', '管理画面からログインしてください。');
        $this->assertGuest('web');
    }
}
