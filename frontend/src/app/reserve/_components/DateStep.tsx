"use client";

import { useEffect, useState } from "react";
import { ApiError, fetchAvailableDates, type DateAvailability } from "@/lib/api";
import { formatDate, weekdayOf, WEEKDAY_LABELS } from "@/lib/date";
import { alertBox, secondaryButton } from "./ui";

type Props = {
  onSelect: (date: string) => void;
};

/** ① 日付を選ぶ（今日〜30日後のカレンダー） */
export default function DateStep({ onSelect }: Props) {
  const [dates, setDates] = useState<DateAvailability[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [reloadCount, setReloadCount] = useState(0);

  // 画面が表示されたとき（と「再読み込み」を押したとき）に、日付の一覧を読み込む
  useEffect(() => {
    let ignore = false; // 読み込み中に画面が切り替わったら、結果を使わない
    fetchAvailableDates()
      .then((result) => {
        if (!ignore) setDates(result);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(e instanceof ApiError ? e.message : "読み込みに失敗しました。");
      });
    return () => {
      ignore = true;
    };
  }, [reloadCount]);

  function reload() {
    setError(null);
    setDates(null);
    setReloadCount((count) => count + 1);
  }

  if (error) {
    return (
      <div className="space-y-4">
        <p className={alertBox} role="alert">
          {error}
        </p>
        <button type="button" className={secondaryButton} onClick={reload}>
          再読み込み
        </button>
      </div>
    );
  }

  if (dates === null) {
    return <p className="py-10 text-center text-gray-500">読み込み中…</p>;
  }

  // カレンダーの1行目を曜日に合わせるための空白マス
  const blanks = dates.length > 0 ? weekdayOf(dates[0].date) : 0;

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold">ご希望の日付を選んでください</h2>
      <p className="mb-4 text-sm text-gray-600">○：空きあり　×：満席　休：休診</p>

      <div className="grid grid-cols-7 gap-1 text-center">
        {WEEKDAY_LABELS.map((label, index) => (
          <div
            key={label}
            className={`py-1 text-xs font-bold ${index === 0 ? "text-red-600" : index === 6 ? "text-blue-600" : "text-gray-600"}`}
          >
            {label}
          </div>
        ))}

        {Array.from({ length: blanks }, (_, index) => (
          <div key={`blank-${index}`} />
        ))}

        {dates.map((day, index) => {
          const [, month, date] = day.date.split("-").map(Number);
          const canSelect = !day.is_closed && day.available;
          const mark = day.is_closed ? "休" : day.available ? "○" : "×";
          const status = day.is_closed ? "休診" : day.available ? "空きあり" : "満席";
          // 最初の日と、月の初日には「11/1」のように月も表示する
          const label = index === 0 || date === 1 ? `${month}/${date}` : `${date}`;

          return (
            <button
              key={day.date}
              type="button"
              disabled={!canSelect}
              onClick={() => onSelect(day.date)}
              aria-label={`${formatDate(day.date)} ${status}`}
              className={`flex min-h-14 flex-col items-center justify-center rounded-md border text-sm ${
                canSelect
                  ? "border-brand bg-white text-gray-900 hover:bg-brand-light"
                  : "border-gray-200 bg-gray-100 text-gray-400"
              }`}
            >
              <span>{label}</span>
              <span className={`text-base font-bold ${canSelect ? "text-brand" : ""}`}>{mark}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
