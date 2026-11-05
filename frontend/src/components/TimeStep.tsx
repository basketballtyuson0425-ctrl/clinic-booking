"use client";

import { useEffect, useState } from "react";
import { ApiError, fetchDaySlots, type DaySlots, type Slot } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/date";
import { alertBox, secondaryButton } from "./ui";

type Period = "am" | "pm";

type Props = {
  date: string;
  notice: string | null; // 前の操作からのお知らせ（例：選んだ時間が埋まってしまった）
  onSelect: (time: string) => void;
  onBack: () => void;
};

/** ② 午前／午後を選び、30分ごとの時間を選ぶ */
export default function TimeStep({ date, notice, onSelect, onBack }: Props) {
  const [day, setDay] = useState<DaySlots | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState<Period | null>(null);

  // 選んだ日の枠を読み込む
  useEffect(() => {
    let ignore = false;
    fetchDaySlots(date)
      .then((result) => {
        if (!ignore) setDay(result);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(e instanceof ApiError ? e.message : "読み込みに失敗しました。");
      });
    return () => {
      ignore = true;
    };
  }, [date]);

  const hasAvailable = (slots: Slot[] | null) => slots?.some((slot) => slot.available) ?? false;
  // 最初は、空きがある方（なければ午前）を表示する
  const activePeriod: Period = period ?? (!hasAvailable(day?.am ?? null) && hasAvailable(day?.pm ?? null) ? "pm" : "am");
  const slots = day ? day[activePeriod] : null;

  return (
    <div className="space-y-4">
      <div>
        <p className="text-base font-bold text-brand-dark">{formatDate(date)}</p>
        <h2 className="text-lg font-bold">ご希望の時間を選んでください</h2>
      </div>

      {notice && (
        <p className={alertBox} role="alert">
          {notice}
        </p>
      )}

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}

      {!error && day === null && <p className="py-10 text-center text-gray-500">読み込み中…</p>}

      {day && (
        <>
          <div className="grid grid-cols-2 gap-2" role="group" aria-label="午前・午後">
            {(["am", "pm"] as const).map((value) => (
              <button
                key={value}
                type="button"
                aria-pressed={activePeriod === value}
                onClick={() => setPeriod(value)}
                className={`min-h-12 rounded-lg border text-base font-bold ${
                  activePeriod === value
                    ? "border-brand bg-brand text-white"
                    : "border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                }`}
              >
                {value === "am" ? "午前" : "午後"}
              </button>
            ))}
          </div>

          {slots === null ? (
            <p className="py-8 text-center text-gray-600">{activePeriod === "am" ? "午前" : "午後"}は休診です</p>
          ) : (
            <div className="grid grid-cols-3 gap-2">
              {slots.map((slot) => (
                <button
                  key={slot.time}
                  type="button"
                  disabled={!slot.available}
                  onClick={() => onSelect(slot.time)}
                  aria-label={`${formatTime(slot.time)} ${slot.available ? "空きあり" : "予約できません"}`}
                  // 日付のカレンダーと同じく、押せる枠だけを灰色の枠の白いマスにして ○ を緑にする
                  className={`flex min-h-14 flex-col items-center justify-center rounded-md border ${
                    slot.available
                      ? "border-gray-300 bg-white hover:border-brand hover:bg-brand-light"
                      : "border-transparent text-gray-400"
                  }`}
                >
                  <span className="text-base">{formatTime(slot.time)}</span>
                  <span className={`text-base font-bold ${slot.available ? "text-brand" : ""}`}>
                    {slot.available ? "○" : "×"}
                  </span>
                </button>
              ))}
            </div>
          )}
        </>
      )}

      <button type="button" className={secondaryButton} onClick={onBack}>
        日付を選び直す
      </button>
    </div>
  );
}
