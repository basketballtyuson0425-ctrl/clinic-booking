import type { ReactNode } from "react";
import type { Metadata } from "next";

export const metadata: Metadata = {
  // 管理画面は検索エンジンに載せない
  robots: { index: false, follow: false },
};

/**
 * 管理画面の枠（PC 中心の広い幅。スマートフォンでも操作できるようにする）。
 * 院名とメニューは、ログイン後の枠（AdminShell のサイドバー）とログイン画面がそれぞれ表示する
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
  return <div className="flex flex-1 flex-col bg-white">{children}</div>;
}
