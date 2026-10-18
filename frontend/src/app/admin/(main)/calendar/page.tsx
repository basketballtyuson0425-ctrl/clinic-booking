import type { Metadata } from "next";
import CalendarView from "../../_components/CalendarView";

export const metadata: Metadata = {
  title: "営業日カレンダー",
};

/** 営業日カレンダー（/admin/calendar?month=2026-11） */
export default async function CalendarPage({ searchParams }: PageProps<"/admin/calendar">) {
  const { month } = await searchParams;
  // "2026-11" の形のときだけ使う（それ以外は今月を表示する）
  const valid = typeof month === "string" && /^\d{4}-(0[1-9]|1[0-2])$/.test(month) ? month : null;

  // 月が変わったら作り直す（選んでいた日・入力途中の内容を残さない）
  return <CalendarView key={valid ?? "current"} month={valid} />;
}
