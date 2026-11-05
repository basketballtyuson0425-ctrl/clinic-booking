<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AvailabilityController as AdminAvailabilityController;
use App\Http\Controllers\Admin\BusinessDayController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\WeeklyScheduleController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ClinicHoursController;
use App\Http\Controllers\ManageReservationController;
use App\Http\Controllers\ReservationController;
use App\Http\Middleware\PreventCaching;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// このファイルの URL には、すべて先頭に /api が付く（例：/api/availability/dates）

// 公開 API（ログイン不要）。機械的な大量アクセスを防ぐため、アクセス回数を制限する
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/availability/dates', [AvailabilityController::class, 'dates']);
    Route::get('/availability/{date}', [AvailabilityController::class, 'show'])
        ->where('date', '\d{4}-\d{2}-\d{2}');

    // 院の受付時間（曜日ごとの基本）。患者側の画面の下の表に使う
    Route::get('/clinic/hours', [ClinicHoursController::class, 'show']);

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

// 管理画面の API。院長は Cookie とセッションでログインするので、
// 管理画面からのリクエストにだけセッションと CSRF 対策（合言葉の確認）を付ける
Route::prefix('/admin')
    ->middleware(EnsureFrontendRequestsAreStateful::class)
    ->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:admin-login');

        // ここから下は、ログインしていないと 401 になる。
        // 患者の個人情報を返すので、ブラウザや途中のサーバーに応答を保存させない（Cache-Control: no-store, private）
        Route::middleware([PreventCaching::class, 'auth:sanctum'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);

            Route::get('/availability/{date}', [AdminAvailabilityController::class, 'show'])
                ->where('date', '\d{4}-\d{2}-\d{2}');

            Route::get('/reservations', [AdminReservationController::class, 'index']);
            Route::post('/reservations', [AdminReservationController::class, 'store']);
            // {reservation} は予約の ID（数字だけ）。Laravel が自動で予約を探し、なければ 404 にする
            Route::prefix('/reservations/{reservation}')
                ->whereNumber('reservation')
                ->group(function () {
                    Route::get('/', [AdminReservationController::class, 'show']);
                    Route::patch('/', [AdminReservationController::class, 'update']);
                    Route::post('/cancel', [AdminReservationController::class, 'cancel']);
                    Route::post('/resend-mail', [AdminReservationController::class, 'resendMail']);
                });

            // 週のカレンダー・ダッシュボード用の予定表（日ごとの営業時間と予約）
            Route::get('/schedule', [ScheduleController::class, 'show']);

            Route::get('/mail-logs', [MailLogController::class, 'index']);

            // 営業日カレンダーと営業時間の変更
            Route::get('/calendar', [CalendarController::class, 'show']);
            Route::prefix('/business-days/{date}')
                ->where(['date' => '\d{4}-\d{2}-\d{2}'])
                ->group(function () {
                    Route::post('/impact', [BusinessDayController::class, 'impact']);
                    Route::put('/', [BusinessDayController::class, 'update']);
                    Route::delete('/', [BusinessDayController::class, 'destroy']);
                });
            Route::get('/weekly-schedules', [WeeklyScheduleController::class, 'index']);
            Route::put('/weekly-schedules', [WeeklyScheduleController::class, 'update']);
            Route::post('/weekly-schedules/impact', [WeeklyScheduleController::class, 'impact']);
        });
    });
