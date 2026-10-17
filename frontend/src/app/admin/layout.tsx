import type { ReactNode } from "react";
import type { Metadata } from "next";
import { clinic } from "@/lib/clinic";

export const metadata: Metadata = {
  // 管理画面は検索エンジンに載せない
  robots: { index: false, follow: false },
};

/**
 * 管理画面の枠（PC 中心の広い幅。スマートフォンでも操作できるようにする）
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <>
      <header className="bg-gray-800 text-white">
        <div className="mx-auto max-w-5xl px-4 py-3 text-lg font-bold">{clinic.name} 管理画面</div>
      </header>
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-6">{children}</main>
    </>
  );
}
