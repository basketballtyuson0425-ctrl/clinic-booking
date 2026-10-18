// 営業時間の表示と入力のための小さな関数

import type { CalendarDay, HoursInput, Period } from "@/lib/adminApi";
import { formatTime } from "@/lib/date";

/** 選べる時刻（30分単位：00:00〜23:30）。予約枠が30分なので、半端な時刻は選べないようにする */
export const TIME_OPTIONS = Array.from(
  { length: 48 },
  (_, index) => `${String(Math.floor(index / 2)).padStart(2, "0")}:${index % 2 === 0 ? "00" : "30"}`,
);

/** 枠を「営業する」にしたときの初期値（要件：前半 9:00〜13:00、後半 16:00〜19:00） */
export const DEFAULT_PERIODS: { am: Period; pm: Period } = {
  am: { start: "09:00", end: "13:00" },
  pm: { start: "16:00", end: "19:00" },
};

/** カレンダーの1日分を、編集欄の初期値にする */
export function hoursFromDay(day: CalendarDay): HoursInput {
  return {
    is_closed: day.is_closed,
    am_start: day.am?.start ?? null,
    am_end: day.am?.end ?? null,
    pm_start: day.pm?.start ?? null,
    pm_end: day.pm?.end ?? null,
  };
}

/** { start: "09:00", end: "13:00" } → "9:00〜13:00"、休み → "休み" */
export function formatPeriod(period: Period | null): string {
  return period === null ? "休み" : `${formatTime(period.start)}〜${formatTime(period.end)}`;
}

/** 入力中の営業時間を、文章にする（例："前半 9:00〜13:00／後半 休み"、休診 → "休診"） */
export function formatHours(hours: HoursInput): string {
  if (hours.is_closed) return "休診";
  const period = (start: string | null, end: string | null) =>
    start !== null && end !== null ? { start, end } : null;
  return `前半 ${formatPeriod(period(hours.am_start, hours.am_end))}／後半 ${formatPeriod(period(hours.pm_start, hours.pm_end))}`;
}
