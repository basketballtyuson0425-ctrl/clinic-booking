// 管理画面の API を呼ぶ関数。
// 院長は Cookie（セッション）でログインするので、データを変える操作には CSRF の合言葉を付けて送る。

import { ApiError, request, type VisitType } from "./api";

export type AdminUser = {
  name: string;
  email: string;
};

export type ReservationStatus = "reserved" | "cancelled";

/** メールの種類（予約完了・日時の変更・キャンセル・営業時間の変更・院長による再送） */
export type MailType = "reserved" | "changed" | "cancelled" | "hours_changed" | "resent";

/** メールの送信記録 1件 */
export type MailLogEntry = {
  id: number;
  type: MailType;
  status: "sent" | "failed";
  to_email: string;
  error_message: string | null;
  created_at: string; // 送信を試した日時 "2026-10-20 09:00"
  reservation?: { id: number; start_at: string; patient_name: string }; // メール送信記録の画面でだけ付く
};

/** 管理画面で扱う予約（院長の画面なので、電話番号・メールアドレスは伏せずに届く） */
export type AdminReservation = {
  id: number;
  start_at: string; // "2026-10-20 10:00"
  end_at: string;
  status: ReservationStatus;
  is_past: boolean; // 終了時刻を過ぎたか（予約中なら「来院済み」と表示する）
  patient_name: string;
  patient_phone: string;
  patient_email: string | null;
  visit_type: VisitType;
  treatment_request: string | null;
  source: "web" | "admin"; // ネット予約／院長が登録
  cancelled_at: string | null;
  cancelled_by: "patient" | "admin" | null;
  has_manage_url: boolean; // 予約管理 URL を発行済みか
  created_at: string;
  last_mail: MailLogEntry | null; // 最後に送ったメール
  mail_logs?: MailLogEntry[]; // 詳細のときだけ付く（新しい順）
};

/** 50件ずつに分けた一覧（Laravel のページ分けの形） */
export type Paginated<T> = {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    from: number | null; // このページの最初が何件目か
    to: number | null;
  };
};

/** 予約一覧の絞り込み条件 */
export type ReservationFilters = {
  date: string; // "2026-10-20"。空ならすべての日
  name: string;
  phone: string;
  status: ReservationStatus | "";
  includePast: boolean;
  page: number;
};

/** 予約1件を返す API の返事 */
export type ReservationResponse = {
  data: AdminReservation;
  changed?: boolean; // 変更したとき：実際に何か変わったか
  mail_sent?: boolean; // メールを送れたか
};

/** 患者情報の入力 */
export type PatientInput = {
  patient_name: string;
  patient_phone: string;
  patient_email: string;
  visit_type: VisitType | "";
  treatment_request: string;
};

/** 院長用の空き枠の1枠 */
export type AdminSlot = {
  time: string; // "09:00"
  reserved: boolean; // 予約中の予約があるか
  available: boolean; // 選べるか（予約がなく、開始時刻が今より後）
};

/** GET /api/admin/availability/{date} */
export type AdminDaySlots = {
  date: string;
  is_closed: boolean;
  am: AdminSlot[] | null; // 休みの枠は null
  pm: AdminSlot[] | null;
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

/** 絞り込み条件を、URL の ? 以降の形にする（空の条件は入れない）。画面の URL と API の両方で使う */
export function reservationFiltersToQuery(filters: Partial<ReservationFilters>): string {
  const params = new URLSearchParams();
  if (filters.date) params.set("date", filters.date);
  if (filters.name) params.set("name", filters.name);
  if (filters.phone) params.set("phone", filters.phone);
  if (filters.status) params.set("status", filters.status);
  if (filters.includePast) params.set("include_past", "1");
  if (filters.page && filters.page > 1) params.set("page", String(filters.page));
  return params.toString();
}

export function fetchReservations(filters: Partial<ReservationFilters>): Promise<Paginated<AdminReservation>> {
  return adminRequest(`/api/admin/reservations?${reservationFiltersToQuery(filters)}`);
}

export function fetchReservation(id: string | number): Promise<ReservationResponse> {
  return adminRequest(`/api/admin/reservations/${id}`);
}

export function createReservation(
  input: PatientInput & { start_at: string; send_mail: boolean },
): Promise<ReservationResponse> {
  return adminRequest("/api/admin/reservations", { method: "POST", body: JSON.stringify(input) });
}

/** 予約を変更する（送った項目だけが変わる） */
export function updateReservation(
  id: number,
  changes: Partial<PatientInput> & { start_at?: string; send_mail?: boolean },
): Promise<ReservationResponse> {
  return adminRequest(`/api/admin/reservations/${id}`, { method: "PATCH", body: JSON.stringify(changes) });
}

export function cancelReservation(id: number, sendMail: boolean): Promise<ReservationResponse> {
  return adminRequest(`/api/admin/reservations/${id}/cancel`, {
    method: "POST",
    body: JSON.stringify({ send_mail: sendMail }),
  });
}

/** 予約管理 URL を作り直して「予約内容のお知らせ」を送り直す（送れたら、以前の URL は使えなくなる） */
export function resendReservationMail(id: number): Promise<ReservationResponse> {
  return adminRequest(`/api/admin/reservations/${id}/resend-mail`, { method: "POST" });
}

export function fetchMailLogs(failedOnly: boolean, page: number): Promise<Paginated<MailLogEntry>> {
  const params = new URLSearchParams();
  if (failedOnly) params.set("failed_only", "1");
  if (page > 1) params.set("page", String(page));
  return adminRequest(`/api/admin/mail-logs?${params.toString()}`);
}

export function fetchAdminDaySlots(date: string): Promise<AdminDaySlots> {
  return adminRequest(`/api/admin/availability/${date}`);
}
