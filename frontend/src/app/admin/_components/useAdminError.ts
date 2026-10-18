"use client";

import { useRouter } from "next/navigation";
import { useCallback } from "react";
import { ApiError } from "@/lib/api";

/**
 * 管理画面の API のエラーを、画面に出す文章にする関数を返す。
 * ログインの期限が切れていた（401）ときは、ログイン画面へ移動する
 */
export function useAdminError(): (e: unknown) => string {
  const router = useRouter();

  return useCallback(
    (e: unknown) => {
      if (e instanceof ApiError && e.status === 401) {
        router.replace("/admin/login");
        return "ログインの有効期限が切れました。もう一度ログインしてください。";
      }
      // サーバーの障害のとき、共通の文章は患者向け（「お電話でご予約ください」）なので、院長向けの文章にする
      if (!(e instanceof ApiError) || e.status >= 500) {
        return "エラーが発生しました。時間をおいてもう一度お試しください。";
      }
      return e.message;
    },
    [router],
  );
}
