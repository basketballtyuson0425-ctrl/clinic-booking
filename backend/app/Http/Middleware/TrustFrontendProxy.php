<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 画面側（Next.js）から転送されたリクエストで、患者の本当の IP アドレスを使う。
 *
 * 本番では「ブラウザ → Vercel（Next.js）→ Laravel」と転送されるので、そのままでは全員が
 * Next.js のサーバーから来たように見え、IP アドレスごとの回数制限が全員まとめて数えられてしまう。
 * そこで Next.js（src/proxy.ts）が、患者の IP アドレスと合言葉を見出しに付けて転送し、
 * Laravel は合言葉が正しいときだけ、その IP アドレスを信じる。
 * 合言葉がないリクエスト（Laravel に直接届いたもの）は、偽装を防ぐため、つながってきた相手の IP アドレスのままにする。
 */
class TrustFrontendProxy
{
    public const SECRET_HEADER = 'X-Clinic-Proxy-Secret';

    public const CLIENT_IP_HEADER = 'X-Clinic-Client-Ip';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('clinic.proxy_secret');
        $given = $request->headers->get(self::SECRET_HEADER);
        $clientIp = $request->headers->get(self::CLIENT_IP_HEADER);

        // hash_equals は、文字の比べ方で合言葉を推測されないよう、いつも同じ時間で比べる
        if (is_string($secret) && $secret !== '' && is_string($given) && hash_equals($secret, $given)
            && filter_var($clientIp, FILTER_VALIDATE_IP) !== false) {
            // $request->ip()（回数制限・セッションの記録で使う）が、患者の IP アドレスを返すようにする
            $request->server->set('REMOTE_ADDR', $clientIp);
        }

        // 合言葉は、このあとの処理やログに残さない
        $request->headers->remove(self::SECRET_HEADER);
        $request->headers->remove(self::CLIENT_IP_HEADER);

        return $next($request);
    }
}
