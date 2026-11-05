import Link from "next/link";
import type { AdminReservation } from "@/lib/adminApi";
import { formatTime } from "@/lib/date";
import { CheckIcon, VISIT_TYPE_LABELS } from "./labels";
import { familyName, mailFailed } from "./schedule";

type Props = {
  reservation: AdminReservation;
  fullName?: boolean; // 幅に余裕があるところ（スマートフォンの縦並び）では氏名をすべて出す
  link?: boolean; // 詳細へのリンクにするか（同じ予約を下の表でもリンクにしている時間軸では、リンクにしない）
  compact?: boolean; // マスが狭い時間軸では「初」を省き、姓を読めるようにする（初回かどうかは下の表に出る）
};

/**
 * 週のカレンダー・時間軸の予約1件。札や背景で囲まず、名前を文字で置く。
 * 予約中は緑、来院済みは灰色と ✓、要連絡は朱色。初回は「初」、メールの送信に失敗していれば朱色の「※」
 */
export default function ScheduleEntry({ reservation, fullName = false, link = true, compact = false }: Props) {
  const color = reservation.needs_contact
    ? "font-bold text-alert"
    : reservation.is_past
      ? "text-gray-500"
      : "font-medium text-brand";

  const content = (
    <>
      {reservation.is_past && <CheckIcon />}
      <span className="truncate">{fullName ? reservation.patient_name : familyName(reservation.patient_name)}</span>
      {/* 見た目は短い印にして、読み上げソフトには言葉で伝える */}
      {reservation.visit_type === "first" && !compact && (
        <span aria-hidden="true" className="shrink-0 text-xs font-normal text-gray-500">
          初
        </span>
      )}
      {mailFailed(reservation) && (
        <span aria-hidden="true" className="ml-auto shrink-0 font-normal text-alert">
          ※
        </span>
      )}
      <span className="sr-only">
        {`（${formatTime(reservation.start_at.split(" ")[1])}・${VISIT_TYPE_LABELS[reservation.visit_type]}`}
        {reservation.is_past && "・来院済み"}
        {reservation.needs_contact && "・要連絡"}
        {mailFailed(reservation) && "・メールの送信に失敗"}
        {"）"}
      </span>
    </>
  );

  const className = `flex min-w-0 items-center gap-1 ${color}`;
  return link ? (
    <Link
      href={`/admin/reservations/${reservation.id}`}
      className={`${className} underline-offset-4 hover:underline`}
    >
      {content}
    </Link>
  ) : (
    <span className={className}>{content}</span>
  );
}
