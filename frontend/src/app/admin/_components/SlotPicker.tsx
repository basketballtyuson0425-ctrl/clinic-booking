"use client";

import { useEffect, useState } from "react";
import { alertBox } from "@/components/ui";
import { fetchAdminDaySlots, type AdminDaySlots, type AdminSlot } from "@/lib/adminApi";
import { formatDate, formatTime, todayString } from "@/lib/date";
import { textInput } from "./styles";
import { useAdminError } from "./useAdminError";

type Props = {
  value: string | null; // 選んだ日時 "2026-10-20 09:00"
  onChange: (startAt: string | null) => void;
  initialDate?: string; // 最初に表示する日（変更のときは今の予約日）
  currentStartAt?: string; // 変更のとき：今の予約の日時（その枠を「現在の予約」と表示する）
  reloadKey?: number; // 増やすと、枠を読み込み直す（選んだ枠が直前に埋まったときなど）
};

/**
 * 院長が予約の日時を選ぶ部品。日付を選ぶと、営業時間内の30分枠が「○ 空き／× 予約済み」付きで並ぶ。
 * 患者側と違い、30日より先の日や1時間を切った枠も選べる
 */
export default function SlotPicker({ value, onChange, initialDate, currentStartAt, reloadKey = 0 }: Props) {
  const toErrorMessage = useAdminError();
  const [date, setDate] = useState(initialDate ?? todayString());
  const [day, setDay] = useState<AdminDaySlots | null>(null);
  const [error, setError] = useState<string | null>(null);

  // 日付を変えたとき（と reloadKey が増えたとき）に、その日の枠を読み込む
  useEffect(() => {
    if (date === "") return;
    let ignore = false;
    fetchAdminDaySlots(date)
      .then((result) => {
        if (ignore) return;
        setDay(result);
        setError(null);
      })
      .catch((e: unknown) => {
        if (ignore) return;
        setDay(null);
        setError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [date, reloadKey, toErrorMessage]);

  function changeDate(next: string) {
    setDate(next);
    setDay(null);
    setError(null);
    onChange(null); // 日付を変えたら、選んでいた時間は取り消す
  }

  return (
    <div className="space-y-4">
      <div>
        <label htmlFor="slotDate" className="mb-1 block text-sm font-bold">
          日付
        </label>
        <input
          id="slotDate"
          type="date"
          min={todayString()}
          value={date}
          onChange={(e) => changeDate(e.target.value)}
          className={`${textInput} max-w-xs`}
        />
      </div>

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}

      {!error && date !== "" && day === null && <p className="text-sm text-gray-500">読み込み中…</p>}

      {day && (
        <div className="space-y-3">
          <p className="text-sm text-gray-600">
            {formatDate(day.date)}　○：空き　×：予約済み　－：時間を過ぎた枠
          </p>
          {day.is_closed ? (
            <p className="rounded-md bg-gray-100 px-4 py-3 text-sm text-gray-700">休診日です。</p>
          ) : (
            (["am", "pm"] as const).map((period) => (
              <SlotGroup
                key={period}
                label={period === "am" ? "午前" : "午後"}
                date={day.date}
                slots={day[period]}
                value={value}
                currentStartAt={currentStartAt}
                onSelect={onChange}
              />
            ))
          )}
        </div>
      )}
    </div>
  );
}

function SlotGroup({
  label,
  date,
  slots,
  value,
  currentStartAt,
  onSelect,
}: {
  label: string;
  date: string;
  slots: AdminSlot[] | null;
  value: string | null;
  currentStartAt?: string;
  onSelect: (startAt: string) => void;
}) {
  if (slots === null) {
    return (
      <p className="text-sm text-gray-600">
        <span className="font-bold">{label}</span>：休診
      </p>
    );
  }

  return (
    <fieldset>
      <legend className="mb-2 text-sm font-bold">{label}</legend>
      <div className="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-8">
        {slots.map((slot) => {
          const startAt = `${date} ${slot.time}`;
          const selected = value === startAt;
          const isCurrent = currentStartAt === startAt;
          const [mark, status] = isCurrent
            ? ["現在", "現在の予約"]
            : slot.available
              ? ["○", "空き"]
              : slot.reserved
                ? ["×", "予約済み"]
                : ["－", "時間を過ぎた枠"];

          return (
            <button
              key={slot.time}
              type="button"
              disabled={!slot.available}
              aria-pressed={selected}
              aria-label={`${formatTime(slot.time)} ${status}`}
              onClick={() => onSelect(startAt)}
              className={`flex min-h-12 flex-col items-center justify-center rounded-md border text-sm ${
                selected
                  ? "border-brand bg-brand font-bold text-white"
                  : slot.available
                    ? "border-brand bg-white hover:bg-brand-light"
                    : isCurrent
                      ? "border-brand-dark bg-brand-light text-brand-dark"
                      : "border-gray-200 bg-gray-100 text-gray-400"
              }`}
            >
              <span>{formatTime(slot.time)}</span>
              <span className="font-bold">{mark}</span>
            </button>
          );
        })}
      </div>
    </fieldset>
  );
}
