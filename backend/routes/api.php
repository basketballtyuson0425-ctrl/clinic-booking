<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ManageReservationController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

// このファイルの URL には、すべて先頭に /api が付く（例：/api/availability/dates）

// 公開 API（ログイン不要）。機械的な大量アクセスを防ぐため、アクセス回数を制限する
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/availability/dates', [AvailabilityController::class, 'dates']);
    Route::get('/availability/{date}', [AvailabilityController::class, 'show'])
        ->where('date', '\d{4}-\d{2}-\d{2}');

    // 予約の登録は、いたずらで大量に入れられないよう、さらに厳しく制限する
    Route::post('/reservations', [ReservationController::class, 'store'])
        ->middleware('throttle:reservation-create');

    // 予約管理ページ。{token} は64文字の英数字（0-9a-f）だけを受け付ける（それ以外は 404）
    Route::prefix('/manage/{token}')
        ->where(['token' => '[0-9a-f]{64}'])
        ->middleware('throttle:manage')
        ->group(function () {
            Route::get('/', [ManageReservationController::class, 'show']);
            Route::patch('/', [ManageReservationController::class, 'update']);
            Route::post('/cancel', [ManageReservationController::class, 'cancel']);
        });
});
