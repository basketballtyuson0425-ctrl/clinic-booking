// 週のカレンダーとダッシュボードの時間軸で共通に使う計算

import type { AdminReservation, ScheduleDay } from "@/lib/adminApi";
import { toMinutes } from "@/lib/date";

/** 予約枠の長さ（分）。サーバーの Reservation::SLOT_MINUTES と同じ */
export const SLOT_MINUTES = 30;

/** 表の1行（または時間軸の1列）。slot は30分枠、break は「どの日も営業していない」時間（昼の休憩など） */
export type ScheduleRow = { kind: "slot"; minutes: number } | { kind: "break"; from: number; to: number };

/** 予約の開始時刻（0時からの分） */
export function startMinutes(reservation: AdminReservation): number {
  return toMinutes(reservation.start_at.split(" ")[1]);
}

/** その時刻の30分枠が、その日の営業時間に入っているか */
export function isOpenAt(day: ScheduleDay, minutes: number): boolean {
  return [day.am, day.pm].some(
    (period) =>
      period !== null && toMinutes(period.start) <= minutes && minutes + SLOT_MINUTES <= toMinutes(period.end),
  );
}

/**
 * 表に並べる時刻を決める。いずれかの日の営業時間に入る枠と、予約がある時刻（営業時間外になった要連絡の予約も含む）を並べ、
 * どの日にも枠がない時間は「休憩」の1行にまとめる（13:00〜16:00 を6行の空白にしないため）
 */
export function buildRows(days: ScheduleDay[]): ScheduleRow[] {
  const times = new Set<number>();
  for (const day of days) {
    for (const period of [day.am, day.pm]) {
      if (period === null) continue;
      for (let m = toMinutes(period.start); m + SLOT_MINUTES <= toMinutes(period.end); m += SLOT_MINUTES) {
        times.add(m);
      }
    }
    for (const reservation of day.reservations) {
      times.add(startMinutes(reservation));
    }
  }

  const rows: ScheduleRow[] = [];
  let previous: number | null = null;
  for (const minutes of [...times].sort((a, b) => a - b)) {
    if (previous !== null && minutes - previous > SLOT_MINUTES) {
      rows.push({ kind: "break", from: previous + SLOT_MINUTES, to: minutes });
    }
    rows.push({ kind: "slot", minutes });
    previous = minutes;
  }
  return rows;
}

/** その日・その時刻の予約（予定表にはキャンセル済みが入らないので、1つの枠に予約は1件だけ） */
export function reservationAt(day: ScheduleDay, minutes: number): AdminReservation | undefined {
  return day.reservations.find((reservation) => startMinutes(reservation) === minutes);
}

/** "山田 太郎" → "山田"。空白がない名前はそのまま（表のマスが狭いので、姓だけを出す） */
export function familyName(name: string): string {
  return name.trim().split(/[\s　]+/)[0];
}

/** 最後に送ったメールが失敗しているか（※ を付ける） */
export function mailFailed(reservation: AdminReservation): boolean {
  return reservation.last_mail?.status === "failed";
}

/** 営業時間外の枠に付ける斜線の背景（休診・営業時間外） */
export const closedCell =
  "bg-gray-50 bg-[repeating-linear-gradient(135deg,transparent_0_7px,rgb(0_0_0/0.05)_7px_8px)]";
