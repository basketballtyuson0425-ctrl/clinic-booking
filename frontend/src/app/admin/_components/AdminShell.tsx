"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import { alertBox } from "@/components/ui";
import { fetchMe, logout, type AdminUser } from "@/lib/adminApi";
import { ApiError } from "@/lib/api";
import { clinic } from "@/lib/clinic";
import { adminQuietButton } from "./styles";

// メニューは「毎日使う予約の画面」と「ときどき使う設定・記録の画面」に分ける
const MENU_GROUPS = [
  {
    label: "予約",
    items: [
      { href: "/admin", label: "ダッシュボード" },
      { href: "/admin/reservations", label: "予約一覧" },
      { href: "/admin/reservations/new", label: "新規登録" },
    ],
  },
  {
    label: "院の設定と記録",
    items: [
      { href: "/admin/calendar", label: "営業日カレンダー" },
      { href: "/admin/mail-logs", label: "メール送信記録" },
    ],
  },
] as const;

/** いま開いている画面のメニューか（予約の詳細を開いているときは「予約一覧」を選択中にする） */
function isActive(pathname: string, href: string): boolean {
  if (href === "/admin" || href === "/admin/reservations/new") return pathname === href;
  if (href === "/admin/reservations") return pathname.startsWith(href) && pathname !== "/admin/reservations/new";
  return pathname.startsWith(href);
}

/**
 * ログイン後の管理画面に共通の枠。ログインの確認・メニュー・ログアウトを受け持つ。
 * メニューは、幅 1024px（lg）以上では左のサイドバー、それより狭いときは本文の上に並べる。
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
      <div className="px-4 py-6">
        <p className={alertBox} role="alert">
          {error}
        </p>
      </div>
    );
  }

  if (user === null) {
    return <p className="py-10 text-center text-gray-500">読み込み中…</p>;
  }

  return (
    <div className="flex flex-1 flex-col lg:flex-row">
      <aside className="border-b border-gray-200 bg-gray-50 lg:w-60 lg:shrink-0 lg:border-r lg:border-b-0">
        {/* 狭い幅：1行目に院名とログアウト、2行目にメニュー。広い幅：上から院名・メニュー・ログアウト（order で並びを入れ替える） */}
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 lg:sticky lg:top-0 lg:h-screen lg:flex-col lg:flex-nowrap lg:items-stretch lg:px-4 lg:py-6">
          <p className="order-1 lg:px-3">
            <span className="block font-bold lg:text-lg">{clinic.name}</span>
            <span className="block text-xs text-gray-500">予約管理</span>
          </p>

          <div className="order-2 ml-auto flex items-center gap-3 text-sm text-gray-600 lg:order-3 lg:mt-auto lg:ml-0 lg:block lg:px-3">
            <span className="block">ログイン中　{user.name}</span>
            <button type="button" className={adminQuietButton} onClick={handleLogout} disabled={loggingOut}>
              ログアウト
            </button>
          </div>

          <nav aria-label="管理メニュー" className="order-3 flex w-full flex-wrap gap-x-1 lg:order-2 lg:mt-6 lg:block lg:space-y-6">
            {MENU_GROUPS.map((group) => (
              <div key={group.label} className="contents lg:block">
                <p className="hidden px-3 pb-2 text-xs text-gray-500 lg:block">{group.label}</p>
                <ul className="contents lg:block lg:space-y-0.5">
                  {group.items.map((item) => {
                    const active = isActive(pathname, item.href);
                    return (
                      <li key={item.href}>
                        <Link
                          href={item.href}
                          aria-current={active ? "page" : undefined}
                          className={`flex min-h-9 items-center rounded-md px-3 text-sm whitespace-nowrap ${
                            active ? "bg-gray-200/70 font-bold text-gray-900" : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                          }`}
                        >
                          {item.label}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))}
          </nav>
        </div>
      </aside>

      <main className="min-w-0 flex-1 px-4 py-6 lg:px-10 lg:py-8">
        <div className="max-w-5xl">{children}</div>
      </main>
    </div>
  );
}
