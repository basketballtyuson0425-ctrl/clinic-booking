import type { ReactNode } from "react";
import AdminShell from "../_components/AdminShell";

/**
 * ログイン後の管理画面の枠（ログインの確認・上部メニュー・ログアウト）。
 * (main) のように ( ) で囲んだフォルダは URL に含まれない。ログイン画面（/admin/login）はこの枠の外に置く
 */
export default function AdminMainLayout({ children }: { children: ReactNode }) {
  return <AdminShell>{children}</AdminShell>;
}
