<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 応答をブラウザや途中のサーバーに保存させない（患者の個人情報を返す管理画面の API 用）。
 * Laravel 標準の cache.headers は GET の応答にしか付かないので、登録・変更（POST・PATCH）の応答にも付けるために用意した
 */
class PreventCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
