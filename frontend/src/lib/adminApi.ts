// 管理画面の API を呼ぶ関数。
// 院長は Cookie（セッション）でログインするので、データを変える操作には CSRF の合言葉を付けて送る。

import { ApiError, request } from "./api";

export type AdminUser = {
  name: string;
  email: string;
};

/** ブラウザの Cookie から値を読む（XSRF-TOKEN は Laravel が JavaScript から読める形で渡してくれる） */
function readCookie(name: string): string | null {
  const found = document.cookie.split("; ").find((item) => item.startsWith(`${name}=`));
  return found ? decodeURIComponent(found.slice(name.length + 1)) : null;
}

/** Laravel から CSRF の合言葉（XSRF-TOKEN の Cookie）をもらう */
async function fetchCsrfCookie(): Promise<void> {
  await request<null>("/sanctum/csrf-cookie");
}

/**
 * 管理画面の API を呼ぶ。GET 以外（データを変える操作）には、合言葉を X-XSRF-TOKEN の見出しに入れて送る。
 * 合言葉の期限が切れていた（419）ときは、合言葉をもらい直して1回だけやり直す。
 */
async function adminRequest<T>(path: string, init: RequestInit = {}, retried = false): Promise<T> {
  const method = (init.method ?? "GET").toUpperCase();
  if (method === "GET") {
    return request<T>(path, init);
  }

  if (readCookie("XSRF-TOKEN") === null) {
    await fetchCsrfCookie();
  }

  try {
    return await request<T>(path, init, { "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") ?? "" });
  } catch (e: unknown) {
    if (e instanceof ApiError && e.status === 419 && !retried) {
      await fetchCsrfCookie();
      return adminRequest<T>(path, init, true);
    }
    throw e;
  }
}

/** ログイン中の院長。ログインしていなければ 401 の ApiError */
export async function fetchMe(): Promise<AdminUser> {
  const body = await adminRequest<{ user: AdminUser }>("/api/admin/me");
  return body.user;
}

export async function login(email: string, password: string): Promise<AdminUser> {
  const body = await adminRequest<{ user: AdminUser }>("/api/admin/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  return body.user;
}

export async function logout(): Promise<void> {
  await adminRequest<null>("/api/admin/logout", { method: "POST" });
}
