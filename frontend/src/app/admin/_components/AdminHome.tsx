"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { alertBox, secondaryButton } from "@/components/ui";
import { fetchMe, logout, type AdminUser } from "@/lib/adminApi";
import { ApiError } from "@/lib/api";

/**
 * 管理画面のトップ。ログインしていなければログイン画面へ移動する。
 * （画面の移動は案内にすぎない。予約などのデータは、ログインしていないと API が返さない）
 */
export default function AdminHome() {
  const router = useRouter();
  const [user, setUser] = useState<AdminUser | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loggingOut, setLoggingOut] = useState(false);

  useEffect(() => {
    let ignore = false;
    fetchMe()
      .then((result) => {
        if (!ignore) setUser(result);
      })
      .catch((e: unknown) => {
        if (ignore) return;
        if (e instanceof ApiError && e.status === 401) {
          router.replace("/admin/login");
        } else {
          setError(e instanceof ApiError ? e.message : "読み込みに失敗しました。");
        }
      });
    return () => {
      ignore = true;
    };
  }, [router]);

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await logout();
    } finally {
      // 失敗しても、ログイン画面へ戻す（ログイン画面で状態を確かめ直す）
      router.replace("/admin/login");
    }
  }

  if (error) {
    return (
      <p className={alertBox} role="alert">
        {error}
      </p>
    );
  }

  if (user === null) {
    return <p className="py-10 text-center text-gray-500">読み込み中…</p>;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl font-bold">ダッシュボード</h1>
        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-600">ログイン中：{user.name}</span>
          <button
            type="button"
            className={`${secondaryButton} w-auto! min-h-10 px-4 py-2 text-sm`}
            onClick={handleLogout}
            disabled={loggingOut}
          >
            ログアウト
          </button>
        </div>
      </div>

      <section className="rounded-lg border border-gray-200 bg-white px-4 py-5">
        <h2 className="font-bold">管理メニュー</h2>
        <p className="mt-2 text-sm text-gray-600">予約の一覧・登録、営業日の設定、メールの送信記録を、ここから開けます。</p>
      </section>
    </div>
  );
}
