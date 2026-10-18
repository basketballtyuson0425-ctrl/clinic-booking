"use client";

import type { HoursInput } from "@/lib/adminApi";
import { DEFAULT_PERIODS, TIME_OPTIONS } from "./hours";
import { textInput } from "./styles";

type Props = {
  id: string; // 入力欄の id の前に付ける文字（同じ画面に何組も並べても重ならないように）
  value: HoursInput;
  errors: Record<string, string | undefined>; // サーバーから返ってきた項目ごとのエラー（am_start など）
  closedLabel: string; // 例：「この日を休診にする」「毎週休診」
  onChange: (value: HoursInput) => void;
};

/**
 * 営業時間の入力欄（休診・前半枠・後半枠）。日付ごとの設定と、曜日ごとの基本設定で共通に使う。
 * 休診にすると、時刻の欄は表示しない（要件：休診日は時間入力欄を表示しない）
 */
export default function HoursFields({ id, value, errors, closedLabel, onChange }: Props) {
  return (
    <div className="space-y-3">
      <label className="flex items-center gap-2 font-bold">
        <input
          type="checkbox"
          checked={value.is_closed}
          onChange={(e) => {
            const closed = e.target.checked;
            // 営業日に戻したとき、時刻が空なら初期値を入れておく
            onChange(
              closed || value.am_start !== null || value.pm_start !== null
                ? { ...value, is_closed: closed }
                : {
                    is_closed: false,
                    am_start: DEFAULT_PERIODS.am.start,
                    am_end: DEFAULT_PERIODS.am.end,
                    pm_start: DEFAULT_PERIODS.pm.start,
                    pm_end: DEFAULT_PERIODS.pm.end,
                  },
            );
          }}
          className="h-4 w-4 accent-brand"
        />
        {closedLabel}
      </label>

      {!value.is_closed && (
        <div className="space-y-3">
          {(["am", "pm"] as const).map((period) => (
            <PeriodRow key={period} id={id} period={period} value={value} errors={errors} onChange={onChange} />
          ))}
          {errors.is_closed && <ErrorText>{errors.is_closed}</ErrorText>}
        </div>
      )}
    </div>
  );
}

/** 前半枠（am）または後半枠（pm）の1行 */
function PeriodRow({
  id,
  period,
  value,
  errors,
  onChange,
}: {
  id: string;
  period: "am" | "pm";
  value: HoursInput;
  errors: Record<string, string | undefined>;
  onChange: (value: HoursInput) => void;
}) {
  const label = period === "am" ? "前半" : "後半";
  const startKey = `${period}_start` as const;
  const endKey = `${period}_end` as const;
  const open = value[startKey] !== null || value[endKey] !== null;
  const periodErrors = [errors[startKey], errors[endKey]].filter((message) => message !== undefined);

  function setOpen(next: boolean) {
    onChange({
      ...value,
      [startKey]: next ? DEFAULT_PERIODS[period].start : null,
      [endKey]: next ? DEFAULT_PERIODS[period].end : null,
    });
  }

  return (
    <div>
      <div className="flex flex-wrap items-center gap-2">
        <label className="flex w-28 items-center gap-2">
          <input
            type="checkbox"
            checked={open}
            onChange={(e) => setOpen(e.target.checked)}
            className="h-4 w-4 accent-brand"
          />
          {label}を営業
        </label>
        {open ? (
          <span className="flex items-center gap-2">
            <TimeSelect id={`${id}-${startKey}`} label={`${label}の開始`} value={value[startKey]} onChange={(time) => onChange({ ...value, [startKey]: time })} />
            〜
            <TimeSelect id={`${id}-${endKey}`} label={`${label}の終了`} value={value[endKey]} onChange={(time) => onChange({ ...value, [endKey]: time })} />
          </span>
        ) : (
          <span className="text-sm text-gray-500">休み</span>
        )}
      </div>
      {periodErrors.map((message) => (
        <ErrorText key={message}>{message}</ErrorText>
      ))}
    </div>
  );
}

function TimeSelect({
  id,
  label,
  value,
  onChange,
}: {
  id: string;
  label: string;
  value: string | null;
  onChange: (time: string | null) => void;
}) {
  return (
    <select
      id={id}
      aria-label={label}
      value={value ?? ""}
      onChange={(e) => onChange(e.target.value === "" ? null : e.target.value)}
      className={`${textInput} w-auto`}
    >
      <option value="">－</option>
      {TIME_OPTIONS.map((time) => (
        <option key={time} value={time}>
          {time}
        </option>
      ))}
    </select>
  );
}

function ErrorText({ children }: { children: string }) {
  return (
    <p className="mt-1 text-sm text-red-700" role="alert">
      {children}
    </p>
  );
}
