// 管理画面の表示名（状態・メールの種類など）と、それを色付きで見せる小さな部品

import type { AdminReservation, MailLogEntry, MailType } from "@/lib/adminApi";
import type { VisitType } from "@/lib/api";
import { formatDateTime, formatTime } from "@/lib/date";

export const VISIT_TYPE_LABELS: Record<VisitType, string> = {
  first: "初回",
  return: "再来",
};

export const MAIL_TYPE_LABELS: Record<MailType, string> = {
  reserved: "予約完了",
  changed: "日時の変更",
  cancelled: "キャンセル",
  hours_changed: "営業時間の変更",
  resent: "予約内容の再送",
};

/** "2026-10-20 09:00" と "2026-10-20 09:30" → "10月20日（火） 9:00〜9:30"（今年でなければ年も付ける） */
export function formatRange(startAt: string, endAt: string): string {
  const year = startAt.slice(0, 4);
  const yearLabel = year === String(new Date().getFullYear()) ? "" : `${year}年`;
  return `${yearLabel}${formatDateTime(startAt)}〜${formatTime(endAt.split(" ")[1])}`;
}

/** "2026-10-20 09:00" → 30分後の "2026-10-20 09:30"（予約枠は30分。選んだ枠の終了時刻を表示するため） */
export function slotEnd(startAt: string): string {
  const [date, time] = startAt.split(" ");
  const [hour, minute] = time.split(":").map(Number);
  const total = hour * 60 + minute + 30;
  return `${date} ${String(Math.floor(total / 60)).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`;
}

/** 予約の状態（「来院済み」は状態として持たず、予約中で終了時刻を過ぎたものをそう呼ぶ） */
export function StatusBadge({ reservation }: { reservation: AdminReservation }) {
  const [label, className] =
    reservation.status === "cancelled"
      ? ["キャンセル済み", "bg-gray-200 text-gray-700"]
      : reservation.is_past
        ? ["来院済み", "bg-blue-50 text-blue-800"]
        : ["予約中", "bg-brand-light text-brand-dark"];

  return <span className={`inline-block rounded px-2 py-0.5 text-xs font-bold whitespace-nowrap ${className}`}>{label}</span>;
}

/** メールの送信結果。失敗は赤で目立たせる */
export function MailResultBadge({ log }: { log: MailLogEntry }) {
  return log.status === "failed" ? (
    <span className="inline-block rounded bg-red-600 px-2 py-0.5 text-xs font-bold whitespace-nowrap text-white">送信失敗</span>
  ) : (
    <span className="inline-block rounded bg-gray-100 px-2 py-0.5 text-xs whitespace-nowrap text-gray-700">送信済み</span>
  );
}
