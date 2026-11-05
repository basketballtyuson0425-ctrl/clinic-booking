"use client";

import { useEffect, useRef } from "react";
import type { ScheduleDay } from "@/lib/adminApi";
import { minutesToTime } from "@/lib/date";
import { buildRows, closedCell, isOpenAt, reservationAt, SLOT_MINUTES } from "./schedule";
import ScheduleEntry from "./ScheduleEntry";

/**
 * ダッシュボードの1日の時間軸。午前・休憩・午後の30分枠を横に並べ、予約の姓と、いまの時刻の縦線を置く。
 * 同じ予約は下の表にも並ぶので、ここは見た目だけの要約にする（読み上げソフトには読ませず、リンクにもしない）
 */
export default function DayTimeline({ day, nowMinutes }: { day: ScheduleDay; nowMinutes: number }) {
  const rows = buildRows([day]);
  const scrollRef = useRef<HTMLDivElement>(null);

  // 狭い幅で横にはみ出すときは、いまの時刻の線が見える位置から始める（最初の1回だけ）
  useEffect(() => {
    const container = scrollRef.current;
    const nowLine = container?.querySelector<HTMLElement>("[data-now]");
    if (container && nowLine && container.scrollWidth > container.clientWidth) {
      container.scrollLeft = nowLine.parentElement!.offsetLeft - container.clientWidth / 2;
    }
  }, []);

  if (rows.length === 0) return null;

  return (
    <div ref={scrollRef} className="overflow-x-auto" aria-hidden="true">
      {/* 各マスは同じ幅で並べ、56px より狭くなるときだけ横にスクロールする（長い名前でマスが広がらないよう、basis-0 にする） */}
      <ol className="flex w-full">
        {rows.map((row, index) => {
          if (row.kind === "break") {
            return (
              <li
                key={`break-${row.from}`}
                className="flex w-12 shrink-0 items-end justify-center pb-3 text-xs text-gray-500"
              >
                休憩
              </li>
            );
          }

          const reservation = reservationAt(day, row.minutes);
          const previous = rows[index - 1];
          const next = rows[index + 1];
          // 1時間ごとと、休憩の後の最初の枠に時刻を出す
          const showTime = row.minutes % 60 === 0 || previous === undefined || previous.kind === "break";
          const isNow = row.minutes <= nowMinutes && nowMinutes < row.minutes + SLOT_MINUTES;
          // 線が二重にならないよう、各マスは左の線だけを引き、まとまり（午前・午後）の最後のマスだけ右の線も引く
          const isLast = next === undefined || next.kind === "break";

          return (
            <li key={row.minutes} className="relative min-w-14 flex-1 basis-0">
              <span className="block h-5 px-1 text-xs text-gray-500 tabular-nums">
                {showTime ? minutesToTime(row.minutes) : ""}
              </span>
              <div
                className={`flex h-10 items-center border-y border-l border-gray-200 px-1.5 text-sm ${
                  showTime ? "border-l-gray-400" : ""
                } ${isLast ? "border-r" : ""} ${isOpenAt(day, row.minutes) ? "" : closedCell}`}
              >
                {reservation && <ScheduleEntry reservation={reservation} link={false} compact />}
              </div>
              {isNow && (
                <span
                  data-now
                  className="absolute top-4 bottom-0 w-0.5 bg-gray-900"
                  style={{ left: `${((nowMinutes - row.minutes) / SLOT_MINUTES) * 100}%` }}
                />
              )}
            </li>
          );
        })}
      </ol>
    </div>
  );
}
