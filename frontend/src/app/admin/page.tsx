import type { Metadata } from "next";
import AdminHome from "./_components/AdminHome";

export const metadata: Metadata = {
  title: "管理画面",
};

/** 管理画面のトップ（/admin） */
export default function AdminPage() {
  return <AdminHome />;
}
