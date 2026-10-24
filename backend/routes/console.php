<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 終了から1年を過ぎた予約を自動削除する（Reservation::prunable() を参照）。
// 患者の操作がほとんどない深夜に、毎日1回実行する。
// 動かすには、サーバーの cron で `php artisan schedule:run` を毎分実行しておく必要がある。
Schedule::command('model:prune')->dailyAt('03:00');
