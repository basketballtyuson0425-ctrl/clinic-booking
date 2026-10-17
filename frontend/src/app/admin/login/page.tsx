import type { Metadata } from "next";
import LoginForm from "../_components/LoginForm";

export const metadata: Metadata = {
  title: "ログイン",
};

/** 院長のログイン画面（/admin/login） */
export default function AdminLoginPage() {
  return (
    <div className="mx-auto max-w-sm">
      <h1 className="mb-6 text-xl font-bold">ログイン</h1>
      <LoginForm />
    </div>
  );
}
