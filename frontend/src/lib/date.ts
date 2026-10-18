const WEEKDAYS = ["日", "月", "火", "水", "木", "金", "土"];

/** "2026-10-20" → 曜日の番号（0=日曜〜6=土曜） */
export function weekdayOf(date: string): number {
  const [year, month, day] = date.split("-").map(Number);
  return new Date(year, month - 1, day).getDay();
}

/** "2026-10-20" → "10月20日（火）" */
export function formatDate(date: string): string {
  const [, month, day] = date.split("-").map(Number);
  return `${month}月${day}日（${WEEKDAYS[weekdayOf(date)]}）`;
}

/** "09:00" → "9:00" */
export function formatTime(time: string): string {
  return time.replace(/^0/, "");
}

/** "2026-10-20 09:00" → "10月20日（火） 9:00" */
export function formatDateTime(startAt: string): string {
  const [date, time] = startAt.split(" ");
  return `${formatDate(date)} ${formatTime(time)}`;
}

/** 今日の日付 "2026-10-20"（ブラウザの時刻で決める） */
export function todayString(): string {
  const now = new Date();
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** "2026-10-20 09:00" が今より後か（ブラウザの時刻で判定する。画面の案内用で、最終的な判定はサーバーが行う） */
export function isFuture(startAt: string): boolean {
  return new Date(startAt.replace(" ", "T")).getTime() > Date.now();
}

export const WEEKDAY_LABELS = WEEKDAYS;
