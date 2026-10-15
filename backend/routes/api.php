<?php

use App\Http\Controllers\AvailabilityController;
use Illuminate\Support\Facades\Route;

// このファイルの URL には、すべて先頭に /api が付く（例：/api/availability/dates）

// 公開 API（ログイン不要）。機械的な大量アクセスを防ぐため、アクセス回数を制限する
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/availability/dates', [AvailabilityController::class, 'dates']);
    Route::get('/availability/{date}', [AvailabilityController::class, 'show'])
        ->where('date', '\d{4}-\d{2}-\d{2}');
});
