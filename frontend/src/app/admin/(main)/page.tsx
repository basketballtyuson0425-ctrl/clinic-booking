import type { Metadata } from "next";
import Dashboard from "../_components/Dashboard";

export const metadata: Metadata = {
  title: "管理画面",
};

/** 管理画面のトップ（/admin） */
export default function AdminPage() {
  return <Dashboard />;
}
