"use client";

import { useEffect, useState } from "react";
import { fetchClinicHours, type WeeklyHours } from "@/lib/api";
import { clinic } from "@/lib/clinic";
import { buildHoursRows, closedDaysText, TABLE_DAYS } from "@/lib/clinicHours";
import { WEEKDAY_LABELS } from "@/lib/date";

/**
 * 患者側のページの下に出す院の案内（住所・電話・受付時間の表）。
 * 初めての方が予約しながら、場所と受付時間を確かめられるようにする
 */
export default function ClinicInfo() {
  const [schedules, setSchedules] = useState<WeeklyHours[] | null>(null);

  // 受付時間は、院長が管理画面で設定した曜日ごとの営業時間から作る（設定を変えれば表も変わる）
  useEffect(() => {
    let ignore = false;
    fetchClinicHours()
      .then((result) => {
        if (!ignore) setSchedules(result);
      })
      // 読み込めなくても予約はできるので、表を出さないだけにする
      .catch(() => {});
    return () => {
      ignore = true;
    };
  }, []);

  // 外部の地図サービスには、院の住所だけを渡す（患者さんの情報は渡さない）
  const mapUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(clinic.address)}`;

  return (
    <footer className="border-t border-gray-300">
      <div className="mx-auto max-w-md space-y-6 px-4 py-8 text-sm text-gray-700">
        <div className="space-y-1">
          <p className="text-base font-bold text-gray-900">{clinic.name}</p>
          {clinic.address && (
            <p>
              {clinic.address}
              <a
                href={mapUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="ml-3 whitespace-nowrap text-brand underline underline-offset-4"
              >
                地図を開く
              </a>
            </p>
          )}
          {clinic.phone && (
            <p>
              電話{" "}
              <a href={`tel:${clinic.phone}`} className="font-bold text-brand underline underline-offset-4">
                {clinic.phone}
              </a>
            </p>
          )}
        </div>

        {schedules && <HoursTable schedules={schedules} />}
      </div>
    </footer>
  );
}

/** 接骨院の看板などでよく見る「受付時間の表」。時間帯ごとの行に、受け付ける曜日の ○ を並べる */
function HoursTable({ schedules }: { schedules: WeeklyHours[] }) {
  const rows = buildHoursRows(schedules);
  if (rows.length === 0) return null;

  return (
    <div className="space-y-2">
      {/* 時間の列だけ幅を決め、残りを7曜日で等分する（曜日の列が右に寄って詰まらないように） */}
      <table className="w-full table-fixed border-y border-gray-300 text-center tabular-nums">
        <caption className="mb-2 text-left font-bold text-gray-900">受付時間</caption>
        <colgroup>
          <col className="w-28" />
          {TABLE_DAYS.map((dayOfWeek) => (
            <col key={dayOfWeek} />
          ))}
        </colgroup>
        <thead>
          <tr className="border-b border-gray-200">
            <th scope="col" className="py-2 text-left font-normal">
              <span className="sr-only">時間</span>
            </th>
            {TABLE_DAYS.map((dayOfWeek) => (
              <th
                key={dayOfWeek}
                scope="col"
                className={`py-2 font-normal ${dayOfWeek === 0 ? "text-red-600" : dayOfWeek === 6 ? "text-blue-600" : ""}`}
              >
                {WEEKDAY_LABELS[dayOfWeek]}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {rows.map((row) => (
            <tr key={row.label}>
              <th scope="row" className="py-2 pr-2 text-left font-normal whitespace-nowrap">
                {row.label}
              </th>
              {row.open.map((open, index) => (
                <td key={TABLE_DAYS[index]} className={open ? "text-brand" : "text-gray-400"}>
                  {/* 見た目は ○ と ―、読み上げソフトには言葉で伝える */}
                  <span aria-hidden="true">{open ? "○" : "―"}</span>
                  <span className="sr-only">{open ? "受付" : "休み"}</span>
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      <p>休診日：{closedDaysText(schedules)}</p>
      <p className="text-gray-500">臨時の休診・受付時間の変更は、予約の日付の画面でご確認ください。</p>
    </div>
  );
}
