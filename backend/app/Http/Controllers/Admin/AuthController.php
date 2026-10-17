<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * 院長のログイン・ログアウト（Sanctum の SPA 認証：Cookie とセッション）
 */
class AuthController extends Controller
{
    /**
     * POST /api/admin/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // セッションは管理画面（SANCTUM_STATEFUL_DOMAINS に登録した画面）からのリクエストにだけ用意される
        if (! $request->hasSession()) {
            abort(403, '管理画面からログインしてください。');
        }

        $request->authenticate();

        // ログインしたらセッション ID を作り直す（セッション固定攻撃の対策）
        $request->session()->regenerate();

        return response()->json(['user' => $this->userData($request->user())]);
    }

    /**
     * POST /api/admin/logout
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        // セッションを破棄し、CSRF の合言葉も作り直す
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * GET /api/admin/me
     * ログイン中の院長の情報。ログインしていなければ 401（auth:sanctum が返す）
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    /**
     * 画面に返す院長の情報（パスワードのハッシュ値などは含めない）
     *
     * @return array{name: string, email: string}
     */
    private function userData(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email];
    }
}
