"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { alertBox } from "@/components/ui";
import { fetchSchedule, type ScheduleDay } from "@/lib/adminApi";
import {
  addDays,
  formatDate,
  formatMonthDay,
  formatTime,
  minutesToTime,
  mondayOf,
  todayString,
  weekdayOf,
  WEEKDAY_LABELS,
} from "@/lib/date";
import { formatDateHeading } from "./labels";
import { buildRows, closedCell, isOpenAt, reservationAt } from "./schedule";
import ScheduleEntry from "./ScheduleEntry";
import { adminQuietButton } from "./styles";
import { useAdminError } from "./useAdminError";

const weekHref = (monday: string) => `/admin/reservations?week=${monday}`;

/**
 * 予約一覧の週のカレンダー（/admin/reservations?week=2026-11-02）。
 * PC では横に7日・縦に30分枠の表、スマートフォン・タブレットでは日ごとの縦並びにする（7列では狭すぎるため）。
 * キャンセル済みの予約は出さない（その枠は空き枠なので、空いているように見せる。キャンセル済みは「一覧」で見られる）
 */
export default function WeekCalendar({ week }: { week: string | null }) {
  const toErrorMessage = useAdminError();
  // URL で週を指定していなければ今週（ブラウザの時刻で決める。サーバーの時計は日本時間とは限らないため）
  const [today] = useState(todayString);
  const monday = mondayOf(week ?? today);
  const [days, setDays] = useState<ScheduleDay[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let ignore = false;
    fetchSchedule(monday, 7)
      .then((result) => {
        if (!ignore) setDays(result);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [monday, toErrorMessage]);

  return (
    <div className="space-y-4">
      <nav aria-label="週の切り替え" className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <Link href={weekHref(addDays(monday, -7))} className={adminQuietButton}>
          ‹ 前の週
        </Link>
        {/* 狭い幅では見出しを1行目に置き、週の移動のボタンを2行目に並べる（見出しが長く、途中で折り返すため） */}
        <h2 className="order-first w-full font-bold sm:order-none sm:w-auto sm:min-w-48 sm:text-center">
          {formatDateHeading(monday)}〜{formatDate(addDays(monday, 6))}
        </h2>
        <Link href={weekHref(addDays(monday, 7))} className={adminQuietButton}>
          次の週 ›
        </Link>
        {monday !== mondayOf(today) && (
          <Link href="/admin/reservations" className={adminQuietButton}>
            今週に戻る
          </Link>
        )}
      </nav>

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}
      {!error && days === null && <p className="py-6 text-center text-gray-500">読み込み中…</p>}

      {days && (
        <>
          <WeekGrid days={days} today={today} />
          <DayList days={days} today={today} />
          <p className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500">
            <span>初＝初回</span>
            <span>✓＝来院済み</span>
            <span>
              <span className="font-bold text-alert">朱色の名前</span>＝要連絡
            </span>
            <span>
              <span className="text-alert">※</span>＝メールの送信に失敗
            </span>
            {/* 斜線は PC の表でだけ使う */}
            <span className="hidden lg:inline">斜線＝休診・営業時間外</span>
            <span>キャンセル済みの予約は「一覧」で確認できます</span>
          </p>
        </>
      )}
    </div>
  );
}

/** その日の予約の件数（キャンセル済みは予定表に入っていないので、数えるだけでよい）か「休診」 */
function dayCountLabel(day: ScheduleDay): string {
  if (day.reservations.length > 0) return `${day.reservations.length}件`;
  return day.is_closed ? "休診" : "";
}

/** PC：横に7日・縦に30分枠の表 */
function WeekGrid({ days, today }: { days: ScheduleDay[]; today: string }) {
  const rows = buildRows(days);

  return (
    <table className="hidden w-full table-fixed border-collapse lg:table">
      <caption className="sr-only">
        {formatMonthDay(days[0].date)}〜{formatMonthDay(days[6].date)}の予約
      </caption>
      <colgroup>
        <col className="w-14" />
        {days.map((day) => (
          <col key={day.date} />
        ))}
      </colgroup>
      <thead>
        <tr>
          <td />
          {days.map((day) => {
            const isToday = day.date === today;
            const weekday = weekdayOf(day.date);
            return (
              <th
                key={day.date}
                scope="col"
                className="border-b border-gray-300 px-2 pb-2 text-left align-bottom font-normal"
              >
                <span
                  className={`block text-xs ${weekday === 0 ? "text-red-600" : weekday === 6 ? "text-blue-600" : "text-gray-500"}`}
                >
                  {WEEKDAY_LABELS[weekday]}
                  {isToday && "（今日）"}
                </span>
                {day.holiday_name && <span className="block truncate text-[10px] text-red-700">{day.holiday_name}</span>}
                <span className="flex items-baseline justify-between gap-1">
                  {/* 今日は数字に下線を引く（営業日カレンダーと同じ印） */}
                  <span className={`text-xl ${isToday ? "border-b-2 border-gray-900 font-bold" : ""}`}>
                    {Number(day.date.slice(8))}
                  </span>
                  <span className="text-xs text-gray-500">{dayCountLabel(day)}</span>
                </span>
              </th>
            );
          })}
        </tr>
      </thead>
      <tbody>
        {rows.length === 0 && (
          <tr>
            <td colSpan={8} className="py-6 text-center text-sm text-gray-500">
              この週は、すべての日が休診です。
            </td>
          </tr>
        )}
        {rows.map((row) =>
          row.kind === "break" ? (
            <tr key={`break-${row.from}`}>
              <td colSpan={8} className="h-7 border-y border-dashed border-gray-300 pl-16 text-xs text-gray-500">
                {minutesToTime(row.from)}〜{minutesToTime(row.to)}　休憩
              </td>
            </tr>
          ) : (
            <tr key={row.minutes} className="h-9">
              <th scope="row" className="pr-3 text-right text-xs font-normal text-gray-500 tabular-nums">
                {minutesToTime(row.minutes)}
              </th>
              {days.map((day) => {
                const reservation = reservationAt(day, row.minutes);
                return (
                  <td
                    key={day.date}
                    // 1時間ごとの線を少し濃くして、時刻を目で追いやすくする
                    className={`border-t border-l px-2 text-sm ${row.minutes % 60 === 0 ? "border-t-gray-300" : "border-t-gray-100"} border-l-gray-200 ${
                      isOpenAt(day, row.minutes) ? "" : closedCell
                    }`}
                  >
                    {reservation && <ScheduleEntry reservation={reservation} />}
                  </td>
                );
              })}
            </tr>
          ),
        )}
      </tbody>
    </table>
  );
}

/** スマートフォン・タブレット：日ごとの縦並び */
function DayList({ days, today }: { days: ScheduleDay[]; today: string }) {
  return (
    <div className="space-y-6 lg:hidden">
      {days.map((day) => (
        <section key={day.date}>
          <h3 className="flex items-baseline justify-between gap-2 border-b border-gray-300 pb-1">
            <span className="font-bold">
              {formatDate(day.date)}
              {day.holiday_name && <span className="ml-2 text-xs font-normal text-red-700">{day.holiday_name}</span>}
            </span>
            <span className="text-xs text-gray-500">
              {day.date === today && "今日　"}
              {dayCountLabel(day)}
            </span>
          </h3>
          {day.reservations.length === 0 ? (
            !day.is_closed && <p className="py-2 text-sm text-gray-500">予約なし</p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {day.reservations.map((reservation) => (
                <li key={reservation.id} className="grid grid-cols-[3.5rem_minmax(0,1fr)] items-center py-2.5">
                  {/* 時刻は ScheduleEntry の中でも読み上げるので、ここは見た目だけ */}
                  <span aria-hidden="true" className="text-sm text-gray-600 tabular-nums">
                    {formatTime(reservation.start_at.split(" ")[1])}
                  </span>
                  <ScheduleEntry reservation={reservation} fullName />
                </li>
              ))}
            </ul>
          )}
        </section>
      ))}
    </div>
  );
}
