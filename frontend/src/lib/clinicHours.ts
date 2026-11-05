import type { WeeklyHours } from "./api";
import { formatTime, WEEKDAY_LABELS } from "./date";

/** 表の列の並び（月曜から始め、休診の多い日曜を右端に置く） */
export const TABLE_DAYS = [1, 2, 3, 4, 5, 6, 0];

export type HoursRow = {
  label: string; // "9:00〜13:00"
  open: boolean[]; // TABLE_DAYS の順に、その時間に受け付けるか
};

type Band = { start: string; end: string };

/** その曜日の受付時間（午前・午後のうち、休診でないもの） */
function bandsOf(day: WeeklyHours): Band[] {
  if (day.is_closed) return [];
  const bands: Band[] = [];
  if (day.am_start && day.am_end) bands.push({ start: day.am_start, end: day.am_end });
  if (day.pm_start && day.pm_end) bands.push({ start: day.pm_start, end: day.pm_end });
  return bands;
}

/**
 * 受付時間の表の行。曜日ごとの時間を「時間帯 × 曜日」の ○ の表にする。
 * 曜日によって時間が違うときは（例：土曜だけ 9:00〜12:00）、その時間帯の行を別に作る
 */
export function buildHoursRows(schedules: WeeklyHours[]): HoursRow[] {
  const byDay = new Map(schedules.map((day) => [day.day_of_week, bandsOf(day)]));

  // すべての曜日の時間帯を、重ならないよう1つずつ集めて、早い順に並べる
  const labels = new Map<string, Band>();
  for (const bands of byDay.values()) {
    for (const band of bands) labels.set(`${band.start}-${band.end}`, band);
  }
  const sorted = [...labels.values()].sort((a, b) => (a.start + a.end).localeCompare(b.start + b.end));

  return sorted.map((band) => ({
    label: `${formatTime(band.start)}〜${formatTime(band.end)}`,
    open: TABLE_DAYS.map(
      (dayOfWeek) =>
        byDay.get(dayOfWeek)?.some((other) => other.start === band.start && other.end === band.end) ?? false,
    ),
  }));
}

/**
 * 休診日の一文。例：「日曜・祝日、土曜午後、第1・第3水曜」
 * 祝日は、院長が日付ごとに営業を設定しない限り休診になる決まりなので、いつも入れる
 */
export function closedDaysText(schedules: WeeklyHours[]): string {
  const sorted = TABLE_DAYS.map((dayOfWeek) => schedules.find((day) => day.day_of_week === dayOfWeek)).filter(
    (day): day is WeeklyHours => day !== undefined,
  );
  const name = (day: WeeklyHours) => `${WEEKDAY_LABELS[day.day_of_week]}曜`;

  // 1日休み（日曜など）と祝日
  const wholeDays = sorted.filter((day) => bandsOf(day).length === 0).map(name);
  const parts = [[...wholeDays, "祝日"].join("・")];

  // 半日だけ休み（土曜午後など）
  for (const day of sorted) {
    if (day.is_closed) continue;
    const hasAm = Boolean(day.am_start && day.am_end);
    const hasPm = Boolean(day.pm_start && day.pm_end);
    if (hasAm && !hasPm) parts.push(`${name(day)}午後`);
    if (!hasAm && hasPm) parts.push(`${name(day)}午前`);
  }

  // 第N週だけ休み（第1・第3水曜など）
  for (const day of sorted) {
    if (bandsOf(day).length > 0 && day.closed_weeks.length > 0) {
      parts.push(`${day.closed_weeks.map((week) => `第${week}`).join("・")}${name(day)}`);
    }
  }

  return parts.join("、");
}
