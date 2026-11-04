import type { Metadata } from "next";
import { clinic } from "@/lib/clinic";
import LoginForm from "../_components/LoginForm";

export const metadata: Metadata = {
  title: "ログイン",
};

/** 院長のログイン画面（/admin/login） */
export default function AdminLoginPage() {
  return (
    <main className="mx-auto w-full max-w-sm px-4 py-12">
      <p className="text-lg font-bold">{clinic.name}</p>
      <h1 className="mb-8 text-sm text-gray-600">予約管理 ログイン</h1>
      <LoginForm />
    </main>
  );
}
