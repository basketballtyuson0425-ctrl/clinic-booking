"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import { alertBox } from "@/components/ui";
import { fetchMe, logout, type AdminUser } from "@/lib/adminApi";
import { ApiError } from "@/lib/api";
import { adminSecondaryButton } from "./styles";

const MENU = [
  { href: "/admin", label: "ダッシュボード" },
  { href: "/admin/reservations", label: "予約一覧" },
  { href: "/admin/reservations/new", label: "新規登録" },
  { href: "/admin/mail-logs", label: "メール送信記録" },
] as const;

/** いま開いている画面のメニューか（予約の詳細を開いているときは「予約一覧」を選択中にする） */
function isActive(pathname: string, href: string): boolean {
  if (href === "/admin" || href === "/admin/reservations/new") return pathname === href;
  if (href === "/admin/reservations") return pathname.startsWith(href) && pathname !== "/admin/reservations/new";
  return pathname.startsWith(href);
}

/**
 * ログイン後の管理画面に共通の枠。ログインの確認・上部メニュー・ログアウトを受け持つ。
 * ログインしていなければログイン画面へ移動する（画面の移動は案内にすぎない。データは、ログインしていないと API が返さない）
 */
export default function AdminShell({ children }: { children: ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
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
          setError(e instanceof ApiError && e.status < 500 ? e.message : "読み込みに失敗しました。");
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
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
        <nav aria-label="管理メニュー">
          <ul className="flex flex-wrap gap-1">
            {MENU.map((item) => {
              const active = isActive(pathname, item.href);
              return (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    aria-current={active ? "page" : undefined}
                    className={`inline-flex min-h-10 items-center rounded-md px-3 text-sm ${
                      active ? "bg-brand font-bold text-white" : "text-gray-700 hover:bg-gray-100"
                    }`}
                  >
                    {item.label}
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>
        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-600">ログイン中：{user.name}</span>
          <button type="button" className={adminSecondaryButton} onClick={handleLogout} disabled={loggingOut}>
            ログアウト
          </button>
        </div>
      </div>

      {children}
    </div>
  );
}
