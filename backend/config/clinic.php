<?php

/*
 * 接骨院の情報（メール本文などで使う）。値は .env で設定する
 */
return [
    'name' => env('CLINIC_NAME', ''),
    'phone' => env('CLINIC_PHONE', ''),
    'address' => env('CLINIC_ADDRESS', ''),

    // 患者側の画面（Next.js）の URL。メールに載せる予約管理ページのリンクに使う
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    // 画面側（Next.js）との合言葉。Next.js の CLINIC_PROXY_SECRET と同じ値にする。
    // 正しい合言葉つきで転送されたリクエストだけ、見出しの IP アドレスを患者の IP アドレスとして使う
    // （App\Http\Middleware\TrustFrontendProxy）。空なら使わない
    'proxy_secret' => env('CLINIC_PROXY_SECRET'),
];
