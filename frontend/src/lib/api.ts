// Laravel の API を呼ぶ関数と、やり取りするデータの型。
// API の呼び出しはこのファイルにまとめ、エラーの扱いを1か所で決める。

/** GET /api/availability/dates の1日分 */
export type DateAvailability = {
  date: string; // "2026-10-20"
  is_closed: boolean;
  available: boolean; // 空き枠が1つ以上あるか
};

/** 30分ごとの1枠 */
export type Slot = {
  time: string; // "09:00"
  available: boolean;
};

/** GET /api/availability/{date} */
export type DaySlots = {
  date: string;
  is_closed: boolean;
  am: Slot[] | null; // 休みの枠は null
  pm: Slot[] | null;
};

export type VisitType = "first" | "return";

/** POST /api/reservations に送る内容 */
export type ReservationInput = {
  patient_name: string;
  patient_phone: string;
  patient_email: string;
  patient_email_confirmation: string;
  visit_type: VisitType;
  treatment_request: string;
  start_at: string; // "2026-10-20 09:00"
};

/** POST /api/reservations の成功時の返事 */
export type ReservationCreated = {
  start_at: string;
  patient_name: string;
  has_email: boolean;
  mail_sent: boolean; // 確認メールを送れたか（メールアドレスがない場合は false）
  manage_token: string;
};

/** 予約管理ページに表示する予約（GET /api/manage/{token}） */
export type ManagedReservation = {
  start_at: string; // "2026-10-20 10:00"
  end_at: string;
  status: "reserved" | "cancelled";
  patient_name: string;
  patient_phone_masked: string; // "090-****-5678"
  patient_email_masked: string | null; // "ha****@example.com"
  visit_type: VisitType;
  treatment_request: string | null;
  has_email: boolean;
  can_modify: boolean; // オンラインで変更・キャンセルできるか
  cannot_modify_reason: "cancelled" | "no_email" | "deadline_passed" | null;
  modifiable_until: string; // 変更・キャンセルの期限（予約時刻の1時間前）
};

/** 予約管理 API の返事 */
export type ManageResponse = {
  reservation: ManagedReservation;
  changed?: boolean; // 変更したとき：実際に何か変わったか
  mail_sent?: boolean; // 変更・キャンセルしたとき：お知らせメールを送れたか
};

/** API がエラーを返したとき（または通信に失敗したとき）に投げるエラー */
export class ApiError extends Error {
  constructor(
    public readonly status: number, // HTTP ステータスコード（通信失敗は 0）
    message: string,
    public readonly fieldErrors: Record<string, string[]> = {}, // 422 のときの項目ごとのエラー
    public readonly reason: string | null = null, // 409・403 のときの理由（slot_taken / same_day / deadline_passed など）
  ) {
    super(message);
  }
}

function defaultMessage(status: number): string {
  if (status === 429) {
    return "アクセスが集中しています。少し時間をおいてから、もう一度お試しください。";
  }
  return "エラーが発生しました。時間をおいてもう一度お試しいただくか、お電話でご予約ください。";
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  let response: Response;
  try {
    response = await fetch(path, {
      ...init,
      headers: {
        Accept: "application/json",
        ...(init.body ? { "Content-Type": "application/json" } : {}),
      },
    });
  } catch {
    throw new ApiError(0, "通信に失敗しました。電波の良い場所で、もう一度お試しください。");
  }

  // 本文が JSON でない場合（サーバーの障害など）も、画面が止まらないようにする
  const body = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(
      response.status,
      body?.message ?? defaultMessage(response.status),
      body?.errors ?? {},
      body?.reason ?? null,
    );
  }

  return body as T;
}

export async function fetchAvailableDates(): Promise<DateAvailability[]> {
  const body = await request<{ dates: DateAvailability[] }>("/api/availability/dates");
  return body.dates;
}

export function fetchDaySlots(date: string): Promise<DaySlots> {
  return request<DaySlots>(`/api/availability/${date}`);
}

export function createReservation(input: ReservationInput): Promise<ReservationCreated> {
  return request<ReservationCreated>("/api/reservations", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function fetchManagedReservation(token: string): Promise<ManageResponse> {
  return request<ManageResponse>(`/api/manage/${token}`);
}

/** 予約日時・施術希望内容を変更する（送った項目だけが変わる） */
export function updateManagedReservation(
  token: string,
  changes: { start_at?: string; treatment_request?: string },
): Promise<ManageResponse> {
  return request<ManageResponse>(`/api/manage/${token}`, {
    method: "PATCH",
    body: JSON.stringify(changes),
  });
}

export function cancelManagedReservation(token: string): Promise<ManageResponse> {
  return request<ManageResponse>(`/api/manage/${token}/cancel`, { method: "POST" });
}
