<?php

/*
 * 接骨院の情報（メール本文などで使う）。値は .env で設定する
 */
return [
    'name' => env('CLINIC_NAME', ''),
    'phone' => env('CLINIC_PHONE', ''),

    // 患者側の画面（Next.js）の URL。メールに載せる予約管理ページのリンクに使う
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),
];
