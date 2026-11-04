import Link from "next/link";
import { Fragment } from "react";
import type { AdminReservation } from "@/lib/adminApi";
import {
  formatDateHeading,
  formatTimeRange,
  MAIL_TYPE_LABELS,
  NeedsContactMark,
  StatusMark,
  VISIT_TYPE_LABELS,
} from "./labels";

type Props = {
  reservations: AdminReservation[];
  // 日付ごとの見出しを付けるか（ダッシュボードの「本日の予約」のように、1日分だけを出すときは付けない）
  groupByDate?: boolean;
};

/** 予約を日付ごとにまとめる（一覧は日時の順に届くので、隣り合う同じ日付をまとめるだけでよい） */
function groupDays(reservations: AdminReservation[]): { date: string; items: AdminReservation[] }[] {
  const groups: { date: string; items: AdminReservation[] }[] = [];
  for (const reservation of reservations) {
    const date = reservation.start_at.split(" ")[0];
    const last = groups.at(-1);
    if (last && last.date === date) {
      last.items.push(reservation);
    } else {
      groups.push({ date, items: [reservation] });
    }
  }
  return groups;
}

/**
 * 予約の一覧。PC では表、スマートフォン・タブレットでは1件ずつ縦に積んだ行で表示する（lg：幅 1024px 以上で表）。
 * 日付は見出しの行に1回だけ出し、各行は時刻だけにする（同じ日付を全行で繰り返すと、読む量が増えるため）。
 * 箱では囲まず、罫線で区切る
 */
export default function ReservationTable({ reservations, groupByDate = true }: Props) {
  if (reservations.length === 0) {
    return <p className="border-y border-gray-200 py-8 text-center text-sm text-gray-600">該当する予約はありません。</p>;
  }

  const groups = groupByDate ? groupDays(reservations) : [{ date: "", items: reservations }];

  return (
    <>
      {/* PC：表 */}
      <table className="hidden w-full text-left text-sm lg:table">
        {/* 列の幅は中身に合わせて自動で決まる。長い氏名やメールアドレスがあっても、
            ほかの列が押しつぶされて縦書きのようにならないよう、見出しは折り返さず、列ごとに最低幅を決めている */}
        <thead className="border-b border-gray-300 text-xs text-gray-500">
          <tr className="whitespace-nowrap">
            <th className="py-2 pr-4 font-normal">時刻</th>
            <th className="py-2 pr-4 font-normal">氏名</th>
            <th className="py-2 pr-4 font-normal">電話番号</th>
            <th className="py-2 pr-4 font-normal">メールアドレス</th>
            <th className="py-2 font-normal">施術希望内容</th>
          </tr>
        </thead>
        <tbody>
          {groups.map((group) => (
            <Fragment key={group.date || "all"}>
              {groupByDate && (
                <tr className="border-b border-gray-300">
                  <th colSpan={5} scope="colgroup" className="pt-5 pb-1 text-base font-bold">
                    {formatDateHeading(group.date)}
                  </th>
                </tr>
              )}
              {group.items.map((reservation) => {
                const cancelled = reservation.status === "cancelled";
                const muted = cancelled || reservation.is_past;
                return (
                  <tr key={reservation.id} className={`border-b border-gray-100 align-top hover:bg-gray-50 ${muted ? "text-gray-500" : ""}`}>
                    <td className="py-2.5 pr-4 whitespace-nowrap tabular-nums">
                      <Link
                        href={`/admin/reservations/${reservation.id}`}
                        // 下線と打ち消し線は同時に指定できない（あとの指定だけが効く）ので、キャンセル済みは打ち消し線だけにする
                        className={cancelled ? "line-through" : "underline decoration-gray-300 underline-offset-4 hover:decoration-current"}
                      >
                        {formatTimeRange(reservation.start_at, reservation.end_at)}
                      </Link>
                    </td>
                    <td className="min-w-32 py-2.5 pr-4">
                      <span className={cancelled ? "line-through" : ""}>{reservation.patient_name}</span>
                      <span className="ml-2 text-xs whitespace-nowrap text-gray-500">{VISIT_TYPE_LABELS[reservation.visit_type]}</span>
                      <Marks reservation={reservation} />
                    </td>
                    <td className="py-2.5 pr-4 whitespace-nowrap tabular-nums">{reservation.patient_phone}</td>
                    <td className="min-w-36 py-2.5 pr-4">
                      <EmailCell reservation={reservation} />
                    </td>
                    <td className="py-2.5">
                      {/* 表のマス（td）には最大幅が効かないことがあるので、中の入れ物で幅を決めて「…」で省略する */}
                      <div className="max-w-48 truncate" title={reservation.treatment_request ?? undefined}>
                        {reservation.treatment_request ?? "－"}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </Fragment>
          ))}
        </tbody>
      </table>

      {/* スマートフォン・タブレット：左に時刻、右に患者の情報を縦に積む */}
      <div className="lg:hidden">
        {groups.map((group) => (
          <section key={group.date || "all"} aria-label={groupByDate ? formatDateHeading(group.date) : undefined}>
            {groupByDate && (
              <h3 className="border-b border-gray-300 pt-5 pb-1 text-base font-bold">{formatDateHeading(group.date)}</h3>
            )}
            <ul>
              {group.items.map((reservation) => {
                const cancelled = reservation.status === "cancelled";
                const muted = cancelled || reservation.is_past;
                return (
                  <li key={reservation.id} className="border-b border-gray-100">
                    <Link
                      href={`/admin/reservations/${reservation.id}`}
                      className={`grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-3 py-3 text-sm hover:bg-gray-50 ${muted ? "text-gray-500" : ""}`}
                    >
                      <span className={`whitespace-nowrap tabular-nums ${cancelled ? "line-through" : ""}`}>
                        {formatTimeRange(reservation.start_at, reservation.end_at)}
                      </span>
                      <span className="space-y-0.5">
                        <span className="block">
                          <span className={`text-base font-bold ${muted ? "" : "text-gray-900"} ${cancelled ? "line-through" : ""}`}>
                            {reservation.patient_name}
                          </span>
                          <span className="ml-2 text-xs text-gray-500">{VISIT_TYPE_LABELS[reservation.visit_type]}</span>
                        </span>
                        <Marks reservation={reservation} />
                        <span className="block tabular-nums">{reservation.patient_phone}</span>
                        <EmailCell reservation={reservation} />
                        {reservation.treatment_request && <span className="block truncate text-gray-600">{reservation.treatment_request}</span>}
                      </span>
                    </Link>
                  </li>
                );
              })}
            </ul>
          </section>
        ))}
      </div>
    </>
  );
}

/** 状態（キャンセル済み・来院済み）と要連絡。どちらもなければ何も出さない */
function Marks({ reservation }: { reservation: AdminReservation }) {
  const showStatus = reservation.status === "cancelled" || reservation.is_past;
  if (!showStatus && !reservation.needs_contact) return null;

  return (
    <span className="flex flex-wrap gap-x-3">
      <StatusMark reservation={reservation} />
      {reservation.needs_contact && <NeedsContactMark />}
    </span>
  );
}

/**
 * メールアドレス。最後に送ったメールが失敗していたときだけ、その旨を朱色で添える
 * （送信できたメールの記録は、予約の詳細とメール送信記録で見られる）
 */
function EmailCell({ reservation }: { reservation: AdminReservation }) {
  if (reservation.patient_email === null) {
    return <span className="block text-gray-400">なし</span>;
  }

  const lastMail = reservation.last_mail;
  return (
    <span className="block">
      <span className="block break-all">{reservation.patient_email}</span>
      {lastMail?.status === "failed" && (
        <span className="block text-xs font-bold text-alert">※ {MAIL_TYPE_LABELS[lastMail.type]}メールの送信に失敗</span>
      )}
    </span>
  );
}
