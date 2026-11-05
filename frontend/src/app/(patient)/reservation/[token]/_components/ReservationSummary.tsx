import { infoList } from "@/components/ui";
import type { ManagedReservation } from "@/lib/api";
import { formatDate, formatTime } from "@/lib/date";

type Props = {
  reservation: ManagedReservation;
};

/** 予約内容の一覧 */
export default function ReservationSummary({ reservation }: Props) {
  const isCancelled = reservation.status === "cancelled";
  const [startDate, startTime] = reservation.start_at.split(" ");
  const rows: [string, string][] = [
    ["氏名", `${reservation.patient_name} 様`],
    ["電話番号", reservation.patient_phone_masked],
    ["メールアドレス", reservation.patient_email_masked ?? "（ご登録なし）"],
    ["初回／再来", reservation.visit_type === "first" ? "初めて" : "2回目以降"],
    ["施術希望内容", reservation.treatment_request ?? "（なし）"],
  ];

  // 白い箱や状態の札は使わず、罫線で区切って、状態は文字で示す
  return (
    <div className={infoList}>
      <div className="flex items-start justify-between gap-2 py-3">
        <div>
          <p className="text-sm text-gray-600">ご予約日時</p>
          <p className={`mt-1 text-lg font-bold ${isCancelled ? "text-gray-400 line-through" : ""}`}>
            {formatDate(startDate)}{" "}
            {/* 「10:00〜10:30」は途中で折り返さない */}
            <span className="whitespace-nowrap">
              {formatTime(startTime)}〜{formatTime(reservation.end_at.split(" ")[1])}
            </span>
          </p>
        </div>
        <span className={`shrink-0 text-sm font-bold ${isCancelled ? "text-gray-500" : "text-brand"}`}>
          {isCancelled ? "キャンセル済み" : "予約中"}
        </span>
      </div>
      <dl className="divide-y divide-gray-200">
        {rows.map(([label, value]) => (
          <div key={label} className="py-3">
            <dt className="text-sm text-gray-600">{label}</dt>
            <dd className="mt-1 whitespace-pre-wrap break-words text-base">{value}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
