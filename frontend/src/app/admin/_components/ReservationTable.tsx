import Link from "next/link";
import type { AdminReservation } from "@/lib/adminApi";
import { formatRange, MAIL_TYPE_LABELS, MailResultBadge, StatusBadge, VISIT_TYPE_LABELS } from "./labels";
import { panel } from "./styles";

/**
 * 予約の一覧。PC では表、スマートフォンではカードで表示する（md：幅 768px 以上で切り替え）
 */
export default function ReservationTable({ reservations }: { reservations: AdminReservation[] }) {
  if (reservations.length === 0) {
    return <p className={`${panel} px-4 py-8 text-center text-sm text-gray-600`}>該当する予約はありません。</p>;
  }

  return (
    <>
      {/* PC：表 */}
      <div className={`${panel} hidden overflow-x-auto md:block`}>
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-xs text-gray-600">
            <tr>
              <th className="px-3 py-2">予約日時</th>
              <th className="px-3 py-2">状態</th>
              <th className="px-3 py-2">氏名</th>
              <th className="px-3 py-2">初回／再来</th>
              <th className="px-3 py-2">電話番号</th>
              <th className="px-3 py-2">メールアドレス</th>
              <th className="px-3 py-2">施術希望内容</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {reservations.map((reservation) => (
              <tr key={reservation.id} className={reservation.status === "cancelled" ? "text-gray-500" : ""}>
                <td className="px-3 py-2 whitespace-nowrap">
                  <Link href={`/admin/reservations/${reservation.id}`} className="font-bold text-brand underline">
                    {formatRange(reservation.start_at, reservation.end_at)}
                  </Link>
                </td>
                <td className="px-3 py-2">
                  <StatusBadge reservation={reservation} />
                </td>
                <td className="px-3 py-2 whitespace-nowrap">{reservation.patient_name}</td>
                <td className="px-3 py-2">{VISIT_TYPE_LABELS[reservation.visit_type]}</td>
                <td className="px-3 py-2 whitespace-nowrap">{reservation.patient_phone}</td>
                <td className="px-3 py-2">
                  <EmailCell reservation={reservation} />
                </td>
                <td className="max-w-56 truncate px-3 py-2" title={reservation.treatment_request ?? undefined}>
                  {reservation.treatment_request ?? "－"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* スマートフォン：カード */}
      <ul className="space-y-2 md:hidden">
        {reservations.map((reservation) => (
          <li key={reservation.id}>
            <Link
              href={`/admin/reservations/${reservation.id}`}
              className={`${panel} block space-y-1 px-4 py-3 text-sm hover:bg-gray-50 ${
                reservation.status === "cancelled" ? "text-gray-500" : ""
              }`}
            >
              {/* 日時は途中で折り返さない（入りきらないときは、状態の表示を次の行に回す） */}
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-bold whitespace-nowrap text-brand">
                  {formatRange(reservation.start_at, reservation.end_at)}
                </span>
                <StatusBadge reservation={reservation} />
              </div>
              <p className="text-base font-bold text-gray-900">
                {reservation.patient_name}
                <span className="ml-2 text-sm font-normal text-gray-600">{VISIT_TYPE_LABELS[reservation.visit_type]}</span>
              </p>
              <p>{reservation.patient_phone}</p>
              <EmailCell reservation={reservation} />
              {reservation.treatment_request && <p className="truncate text-gray-600">{reservation.treatment_request}</p>}
            </Link>
          </li>
        ))}
      </ul>
    </>
  );
}

/** メールアドレスと、最後に送ったメールの結果 */
function EmailCell({ reservation }: { reservation: AdminReservation }) {
  if (reservation.patient_email === null) {
    return <span className="text-gray-400">なし</span>;
  }

  const lastMail = reservation.last_mail;
  return (
    <div className="space-y-1">
      <p className="break-all">{reservation.patient_email}</p>
      {lastMail && (
        <p className="flex flex-wrap items-center gap-1 text-xs text-gray-600">
          {MAIL_TYPE_LABELS[lastMail.type]}
          <MailResultBadge log={lastMail} />
        </p>
      )}
    </div>
  );
}
