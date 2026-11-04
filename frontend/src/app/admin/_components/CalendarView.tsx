"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { alertBox } from "@/components/ui";
import { fetchCalendar, type CalendarDay } from "@/lib/adminApi";
import { formatTime, todayString, weekdayOf, WEEKDAY_LABELS } from "@/lib/date";
import DayEditor from "./DayEditor";
import { adminQuietButton } from "./styles";
import { useAdminError } from "./useAdminError";
import WeeklyEditor from "./WeeklyEditor";

/** "2026-11" の前後の月（例："2026-10"・"2026-12"） */
function shiftMonth(month: string, diff: number): string {
  const [year, monthNumber] = month.split("-").map(Number);
  const date = new Date(year, monthNumber - 1 + diff, 1);
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
}

/**
 * 営業日カレンダー（/admin/calendar?month=2026-11）。
 * 日付を押すと、カレンダーの下にその日の営業時間が開く（別の画面にはしない：要件20〜24）
 */
export default function CalendarView({ month: requestedMonth }: { month: string | null }) {
  const toErrorMessage = useAdminError();
  // URL で月を指定していなければ今月（ブラウザの時刻で決める。サーバーの時計は日本時間とは限らないため）
  const [month] = useState(() => requestedMonth ?? todayString().slice(0, 7));
  const [days, setDays] = useState<CalendarDay[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  // 月を変えたとき・営業時間を保存したときに、カレンダーを読み込み直す
  useEffect(() => {
    let ignore = false;
    fetchCalendar(month)
      .then((result) => {
        if (ignore) return;
        setDays(result);
        setError(null);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [month, reloadKey, toErrorMessage]);

  const today = todayString();
  const [year, monthNumber] = month.split("-").map(Number);
  const selectedDay = days?.find((day) => day.date === selected) ?? null;
  const reload = () => setReloadKey((key) => key + 1);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">営業日カレンダー</h1>
        <nav aria-label="月の切り替え" className="flex items-center gap-2">
          <Link href={`/admin/calendar?month=${shiftMonth(month, -1)}`} className={adminQuietButton}>
            ‹ 前の月
          </Link>
          <span className="min-w-28 text-center font-bold">
            {year}年{monthNumber}月
          </span>
          <Link href={`/admin/calendar?month=${shiftMonth(month, 1)}`} className={adminQuietButton}>
            次の月 ›
          </Link>
        </nav>
      </div>

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}
      {!error && days === null && <p className="py-6 text-center text-gray-500">読み込み中…</p>}

      {days && (
        <div>
          {/* マスを箱で並べず、罫線の格子にする（上と左の線は外枠、右と下の線は各マスが引く） */}
          <div className="grid grid-cols-7 border-t border-l border-gray-200 text-center">
            {WEEKDAY_LABELS.map((label, index) => (
              <div
                key={label}
                className={`border-r border-b border-gray-300 py-1 text-xs ${index === 0 ? "text-red-600" : index === 6 ? "text-blue-600" : "text-gray-500"}`}
              >
                {label}
              </div>
            ))}

            {/* 1日の曜日に合わせて、前を空ける */}
            {Array.from({ length: weekdayOf(days[0].date) }, (_, index) => (
              <div key={`blank-${index}`} className="border-r border-b border-gray-200" />
            ))}

            {days.map((day) => (
              <DayCell
                key={day.date}
                day={day}
                isToday={day.date === today}
                isSelected={day.date === selected}
                onSelect={() => setSelected(day.date)}
              />
            ))}
          </div>
          <p className="mt-2 text-xs text-gray-500">
            日付を押すと、その日の営業時間を確認・変更できます。「個別」はその日だけの設定がある日です。
          </p>
        </div>
      )}

      {/* 別の日を選んだら作り直す（前の日の入力途中の内容を残さない） */}
      {selectedDay && <DayEditor key={selectedDay.date} day={selectedDay} onSaved={reload} />}

      <WeeklyEditor onSaved={reload} />
    </div>
  );
}

function DayCell({
  day,
  isToday,
  isSelected,
  onSelect,
}: {
  day: CalendarDay;
  isToday: boolean;
  isSelected: boolean;
  onSelect: () => void;
}) {
  const dayNumber = Number(day.date.slice(8));
  // 前半・後半を1行ずつ（例：["9:00-13:00", "16:00-19:00"]、休みの枠は "休"）
  const periodLabels = [day.am, day.pm].map((period) =>
    period ? `${formatTime(period.start)}-${formatTime(period.end)}` : "休",
  );
  const hoursLabel = day.is_closed ? "休診" : periodLabels.join(" / ");

  return (
    <button
      type="button"
      onClick={onSelect}
      aria-pressed={isSelected}
      aria-label={`${day.date} ${day.holiday_name ?? ""} ${day.is_closed ? "休診" : hoursLabel} 予約${day.reservation_count}件${
        day.needs_contact_count > 0 ? ` 要連絡${day.needs_contact_count}件` : ""
      }`}
      className={`flex min-h-16 flex-col items-stretch gap-0.5 border-r border-b border-gray-200 p-1 text-left sm:p-1.5 text-xs sm:min-h-24 ${
        isSelected
          ? "bg-brand-light outline-2 -outline-offset-2 outline-brand"
          : day.is_closed
            ? "bg-gray-50 text-gray-500 hover:bg-gray-100"
            : "bg-white hover:bg-gray-50"
      }`}
    >
      <span className="flex items-center justify-between">
        {/* 今日は数字に下線を引く（塗りつぶすと、選んだ日の色とまぎらわしいため） */}
        <span className={`text-sm ${isToday ? "border-b-2 border-gray-900 font-bold" : ""}`}>{dayNumber}</span>
        {day.has_override && <span className="text-[10px] text-gray-500">個別</span>}
      </span>
      {day.holiday_name && <span className="truncate text-[10px] text-red-700">{day.holiday_name}</span>}
      {/* スマートフォンでは幅が狭いので、営業時間は「営」「休」だけにする */}
      <span className="hidden leading-tight sm:block">
        {day.is_closed
          ? "休診"
          : periodLabels.map((label, index) => (
              <span key={index} className="block">
                {label}
              </span>
            ))}
      </span>
      <span className="font-bold sm:hidden">{day.is_closed ? "休" : "営"}</span>
      {day.reservation_count > 0 && <span className="text-gray-700">予約{day.reservation_count}</span>}
      {day.needs_contact_count > 0 && (
        <span className="font-bold text-alert">要連絡{day.needs_contact_count}</span>
      )}
    </button>
  );
}
