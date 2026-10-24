<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ModelsPruned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 公開 API のアクセス回数の制限：同じ IP アドレスから1分あたり60回まで
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // 予約の登録：同じ IP アドレスから1分あたり10回まで
        RateLimiter::for('reservation-create', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // 予約管理ページの API：同じ IP アドレスから1分あたり30回まで
        RateLimiter::for('manage', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // 院長のログイン API：同じ IP アドレスから1分あたり10回まで（メールアドレスを変えながら試す攻撃への対策）
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // 自動削除（model:prune）が動いたことを後から確かめられるよう、件数だけをログに残す。
        // 個人情報は書かない。count は1000件ごとの途中経過で、それまでの合計が入る。
        // 0件のときは Laravel がこの合図を出さないので、ログも書かれない。
        // model:prune は終わりにこの合図の受け取り手をすべて外すが、schedule:run は毎回
        // 別のプロセスでコマンドを動かすので、次の日の実行には影響しない。
        Event::listen(function (ModelsPruned $event) {
            Log::info('古いデータを自動削除しました', ['model' => $event->model, 'count' => $event->count]);
        });
    }
}
