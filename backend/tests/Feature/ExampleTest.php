<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Laravel 側のトップページと、動作確認用の URL を確かめる
 */
class ExampleTest extends TestCase
{
    public function test_トップページは表示しない(): void
    {
        // 画面は Next.js が表示する。Laravel は API だけなので、/ にはページを置かない
        $this->get('/')->assertNotFound();
    }

    public function test_動作確認用の_url_は表示する(): void
    {
        $this->get('/up')->assertOk();
    }
}
