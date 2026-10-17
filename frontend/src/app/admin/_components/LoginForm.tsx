"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import { alertBox, primaryButton } from "@/components/ui";
import { fetchMe, login } from "@/lib/adminApi";
import { ApiError } from "@/lib/api";

const inputClass =
  "block w-full rounded-md border border-gray-300 bg-white px-3 py-3 text-base focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-light";

/** メールアドレスとパスワードでログインする */
export default function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [errors, setErrors] = useState<string[]>([]);
  const [submitting, setSubmitting] = useState(false);

  // すでにログインしていたら、管理画面へ移動する
  useEffect(() => {
    let ignore = false;
    fetchMe()
      .then(() => {
        if (!ignore) router.replace("/admin");
      })
      .catch(() => {
        // ログインしていない（401）ので、このままログイン画面を表示する
      });
    return () => {
      ignore = true;
    };
  }, [router]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setErrors([]);
    try {
      await login(email.trim(), password);
      router.replace("/admin");
    } catch (e: unknown) {
      if (e instanceof ApiError) {
        const fieldMessages = Object.values(e.fieldErrors).flat();
        setErrors(fieldMessages.length > 0 ? fieldMessages : [e.message]);
      } else {
        setErrors(["エラーが発生しました。時間をおいてもう一度お試しください。"]);
      }
      setPassword(""); // 失敗したら、パスワードは入力し直してもらう
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-5">
      {errors.length > 0 && (
        <div className={alertBox} role="alert">
          {errors.map((message) => (
            <p key={message}>{message}</p>
          ))}
        </div>
      )}

      <div>
        <label htmlFor="email" className="mb-2 block font-bold">
          メールアドレス
        </label>
        <input
          id="email"
          type="email"
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className={inputClass}
        />
      </div>

      <div>
        <label htmlFor="password" className="mb-2 block font-bold">
          パスワード
        </label>
        <input
          id="password"
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className={inputClass}
        />
      </div>

      <button type="submit" className={primaryButton} disabled={submitting}>
        {submitting ? "確認中…" : "ログイン"}
      </button>
    </form>
  );
}
