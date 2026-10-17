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

  return (
    <div className="rounded-lg border border-gray-200 bg-white">
      <div className="flex items-start justify-between gap-2 border-b border-gray-200 px-4 py-3">
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
        <span
          className={`shrink-0 rounded-full px-3 py-1 text-xs font-bold ${
            isCancelled ? "bg-gray-200 text-gray-600" : "bg-brand text-white"
          }`}
        >
          {isCancelled ? "キャンセル済み" : "予約中"}
        </span>
      </div>
      <dl className="divide-y divide-gray-200">
        {rows.map(([label, value]) => (
          <div key={label} className="px-4 py-3">
            <dt className="text-sm text-gray-600">{label}</dt>
            <dd className="mt-1 whitespace-pre-wrap break-words text-base">{value}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
