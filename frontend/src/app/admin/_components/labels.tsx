// 管理画面の表示名（状態・メールの種類など）と、それを文字で見せる小さな部品。
// 札（色付きの小さな箱）は使わない。いつもの状態（予約中・送信済み）は目立たせず、
// 院長の対応が必要なもの（要連絡・送信失敗）だけを朱色の文字にする

import type { AdminReservation, MailLogEntry, MailType } from "@/lib/adminApi";
import type { VisitType } from "@/lib/api";
import { formatDate, formatDateTime, formatTime } from "@/lib/date";

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

/** 今年でなければ「2027年」を付ける */
function yearPrefix(date: string): string {
  const year = date.slice(0, 4);
  return year === String(new Date().getFullYear()) ? "" : `${year}年`;
}

/** "2026-10-20 09:00" と "2026-10-20 09:30" → "10月20日（火） 9:00〜9:30"（今年でなければ年も付ける） */
export function formatRange(startAt: string, endAt: string): string {
  return `${yearPrefix(startAt)}${formatDateTime(startAt)}〜${formatTime(endAt.split(" ")[1])}`;
}

/** "2026-10-20 09:00" と "2026-10-20 09:30" → "9:00〜9:30"（日付は見出しに出すときに使う） */
export function formatTimeRange(startAt: string, endAt: string): string {
  return `${formatTime(startAt.split(" ")[1])}〜${formatTime(endAt.split(" ")[1])}`;
}

/** "2026-10-20" → "10月20日（火）"（今年でなければ年も付ける） */
export function formatDateHeading(date: string): string {
  return `${yearPrefix(date)}${formatDate(date)}`;
}

/** "2026-10-20 09:00" → 30分後の "2026-10-20 09:30"（予約枠は30分。選んだ枠の終了時刻を表示するため） */
export function slotEnd(startAt: string): string {
  const [date, time] = startAt.split(" ");
  const [hour, minute] = time.split(":").map(Number);
  const total = hour * 60 + minute + 30;
  return `${date} ${String(Math.floor(total / 60)).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`;
}

/** チェックの印（来院済み） */
export function CheckIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className="inline-block h-4 w-4 fill-none stroke-current stroke-2">
      <path d="M20 6 9 17l-5-5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

/**
 * 予約の状態。予約中（これからの予約）は何も表示しない。
 * 「来院済み」は状態として持たず、予約中で終了時刻を過ぎたものをそう呼ぶ
 */
export function StatusMark({ reservation }: { reservation: AdminReservation }) {
  if (reservation.status === "cancelled") {
    return <span className="text-xs whitespace-nowrap text-gray-500">キャンセル済み</span>;
  }
  if (reservation.is_past) {
    return (
      <span className="inline-flex items-center gap-1 text-xs whitespace-nowrap text-gray-500">
        <CheckIcon />
        来院済み
      </span>
    );
  }
  return null;
}

/** 要連絡（営業時間の変更で、今の営業時間から外れた予約）。院長が電話で連絡し、日時を変えるかキャンセルする */
export function NeedsContactMark() {
  return <span className="text-xs font-bold whitespace-nowrap text-alert">要連絡</span>;
}

/** メールの送信結果。失敗は朱色で目立たせ、送信済みは控えめにする */
export function MailResultMark({ log }: { log: MailLogEntry }) {
  return log.status === "failed" ? (
    <span className="font-bold whitespace-nowrap text-alert">※ 送信失敗</span>
  ) : (
    <span className="whitespace-nowrap text-gray-500">送信済み</span>
  );
}
