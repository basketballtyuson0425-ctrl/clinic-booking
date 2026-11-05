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

/** "2026-11-02" → "11月2日"（曜日を付けない短い形） */
export function formatMonthDay(date: string): string {
  const [, month, day] = date.split("-").map(Number);
  return `${month}月${day}日`;
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

/** Date → "2026-10-20" */
function toDateString(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** 今日の日付 "2026-10-20"（ブラウザの時刻で決める） */
export function todayString(): string {
  return toDateString(new Date());
}

/** "2026-10-30" の n 日後（n が負なら n 日前）。月や年をまたいでも正しく数える（例：10/30 の 3 日後は 11/2） */
export function addDays(date: string, days: number): string {
  const [year, month, day] = date.split("-").map(Number);
  return toDateString(new Date(year, month - 1, day + days));
}

/** その日を含む週の月曜日（管理画面の週は月曜始まり。休診の多い日曜を右端に置くため） */
export function mondayOf(date: string): string {
  // 月曜なら 0 日、日曜なら 6 日さかのぼる
  return addDays(date, -((weekdayOf(date) + 6) % 7));
}

/** "09:30" → 570（0時からの分。時刻の比較・計算に使う） */
export function toMinutes(time: string): number {
  const [hour, minute] = time.split(":").map(Number);
  return hour * 60 + minute;
}

/** 570 → "9:30" */
export function minutesToTime(minutes: number): string {
  return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`;
}

/** "2026-10-20 09:00" が今より後か（ブラウザの時刻で判定する。画面の案内用で、最終的な判定はサーバーが行う） */
export function isFuture(startAt: string): boolean {
  return new Date(startAt.replace(" ", "T")).getTime() > Date.now();
}

export const WEEKDAY_LABELS = WEEKDAYS;
