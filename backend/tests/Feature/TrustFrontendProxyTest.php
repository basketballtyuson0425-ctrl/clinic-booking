<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 画面側（Next.js）から転送されたリクエストで、患者の本当の IP アドレスを使う仕組みを確かめる
 */
class TrustFrontendProxyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-proxy-secret-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config(['clinic.proxy_secret' => self::SECRET]);

        // Laravel から見た IP アドレスと、合言葉の見出しが残っているかを返す確認用の URL
        Route::get('/_test/client', fn (Request $request) => [
            'ip' => $request->ip(),
            'secret_header' => $request->header('X-Clinic-Proxy-Secret'),
        ]);
    }

    public function test_合言葉が正しければ転送元から届いた患者の_ip_アドレスを使う(): void
    {
        $this->withHeaders(['X-Clinic-Proxy-Secret' => self::SECRET, 'X-Clinic-Client-Ip' => '203.0.113.10'])
            ->getJson('/_test/client')
            ->assertJson(['ip' => '203.0.113.10']);
    }

    public function test_合言葉の見出しはこのあとの処理に残さない(): void
    {
        $this->withHeaders(['X-Clinic-Proxy-Secret' => self::SECRET, 'X-Clinic-Client-Ip' => '203.0.113.10'])
            ->getJson('/_test/client')
            ->assertJson(['secret_header' => null]);
    }

    public function test_合言葉が違えば送られてきた_ip_アドレスは使わない(): void
    {
        $this->withHeaders(['X-Clinic-Proxy-Secret' => 'wrong-secret', 'X-Clinic-Client-Ip' => '203.0.113.10'])
            ->getJson('/_test/client')
            ->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_合言葉がなければ送られてきた_ip_アドレスは使わない(): void
    {
        $this->withHeaders(['X-Clinic-Client-Ip' => '203.0.113.10'])
            ->getJson('/_test/client')
            ->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_合言葉を設定していなければ見出しがあっても使わない(): void
    {
        config(['clinic.proxy_secret' => null]);

        $this->withHeaders(['X-Clinic-Proxy-Secret' => '', 'X-Clinic-Client-Ip' => '203.0.113.10'])
            ->getJson('/_test/client')
            ->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_ip_アドレスの形でなければ使わない(): void
    {
        $this->withHeaders(['X-Clinic-Proxy-Secret' => self::SECRET, 'X-Clinic-Client-Ip' => '203.0.113.10, 198.51.100.1'])
            ->getJson('/_test/client')
            ->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_回数制限は患者の_ip_アドレスごとに数える(): void
    {
        $patientA = ['X-Clinic-Proxy-Secret' => self::SECRET, 'X-Clinic-Client-Ip' => '203.0.113.10'];
        $patientB = ['X-Clinic-Proxy-Secret' => self::SECRET, 'X-Clinic-Client-Ip' => '203.0.113.20'];

        // 予約の登録は同じ IP アドレスから1分あたり10回まで（中身が空なので 422 になるが、回数には数えられる）
        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders($patientA)->postJson('/api/reservations', [])->assertStatus(422);
        }

        $this->withHeaders($patientA)->postJson('/api/reservations', [])->assertStatus(429);
        // 同じ Next.js のサーバーを通ってきても、別の患者は制限されない
        $this->withHeaders($patientB)->postJson('/api/reservations', [])->assertStatus(422);
    }
}
