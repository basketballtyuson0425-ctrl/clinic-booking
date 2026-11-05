"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { alertBox } from "@/components/ui";
import { fetchReservations, fetchSchedule, type AdminReservation, type ScheduleDay } from "@/lib/adminApi";
import { addDays, formatDate, formatMonthDay, formatTime, mondayOf, todayString } from "@/lib/date";
import DayTimeline from "./DayTimeline";
import { VISIT_TYPE_LABELS } from "./labels";
import ReservationTable from "./ReservationTable";
import { startMinutes } from "./schedule";
import { useAdminError } from "./useAdminError";

const MENU = [
  { href: "/admin/reservations", label: "予約一覧", description: "予約の確認・検索、日時の変更・キャンセル" },
  { href: "/admin/reservations/new", label: "新規登録", description: "お電話などで受けた予約を登録します" },
  { href: "/admin/calendar", label: "営業日カレンダー", description: "日ごと・曜日ごとの営業時間の変更" },
  { href: "/admin/mail-logs", label: "メール送信記録", description: "患者さんに送ったメールと、送信の失敗" },
];

/** いまの時刻（0時からの分。ブラウザの時刻） */
function currentMinutes(): number {
  const now = new Date();
  return now.getHours() * 60 + now.getMinutes();
}

/** 期間内の予約の件数（予定表にはキャンセル済みが入っていないので、数えるだけでよい） */
function countReservations(days: ScheduleDay[]): number {
  return days.reduce((sum, day) => sum + day.reservations.length, 0);
}

/** 管理画面のトップ（/admin）。今日・今週・来週の件数、要連絡の予約、本日の予約と時間軸、各画面へのメニュー */
export default function Dashboard() {
  const toErrorMessage = useAdminError();
  const [today] = useState(todayString);
  const monday = mondayOf(today);
  const [days, setDays] = useState<ScheduleDay[] | null>(null);
  const [needsContact, setNeedsContact] = useState<AdminReservation[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [nowMinutes, setNowMinutes] = useState(currentMinutes);

  useEffect(() => {
    let ignore = false;
    // 今週の月曜から14日分（今週と来週）の予定表と、要連絡の予約（営業時間の変更で、営業時間外になった予約）を同時に読み込む
    Promise.all([fetchSchedule(monday, 14), fetchReservations({ needsContact: true })])
      .then(([schedule, contacts]) => {
        if (ignore) return;
        setDays(schedule);
        setNeedsContact(contacts.data);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [monday, toErrorMessage]);

  // 時間軸の「いま」の線と「次の予約」を、1分ごとに進める
  useEffect(() => {
    const timer = setInterval(() => setNowMinutes(currentMinutes()), 60_000);
    return () => clearInterval(timer);
  }, []);

  const todayDay = days?.find((day) => day.date === today) ?? null;

  return (
    <div className="space-y-12">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h1 className="text-2xl font-bold">ダッシュボード</h1>
        <p className="text-sm text-gray-500">{formatDate(today)}</p>
      </div>

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}
      {!error && days === null && <p className="py-6 text-center text-gray-500">読み込み中…</p>}

      {days && todayDay && <Figures days={days} todayDay={todayDay} monday={monday} />}

      {/* 要連絡は箱で囲まず、見出しの件数だけを朱色にする */}
      {needsContact.length > 0 && (
        <section className="space-y-3">
          <h2 className="text-lg font-bold">
            要連絡の予約　<span className="text-alert">{needsContact.length}</span> 件
          </h2>
          <p className="max-w-2xl text-sm text-gray-600">
            営業時間の変更で、営業時間外になった予約です。患者さんにお電話で連絡し、日時を変更するか、キャンセルしてください。
          </p>
          <ReservationTable reservations={needsContact} />
        </section>
      )}

      {todayDay && (
        <section className="space-y-4">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h2 className="text-lg font-bold">本日の予約</h2>
            <p className="text-sm text-gray-500">キャンセル済みを除く</p>
          </div>
          {todayDay.is_closed && todayDay.reservations.length === 0 ? (
            <p className="border-y border-gray-300 py-4 text-sm text-gray-600">本日は休診日です。</p>
          ) : (
            <>
              <NextReservation reservations={todayDay.reservations} nowMinutes={nowMinutes} />
              <DayTimeline day={todayDay} nowMinutes={nowMinutes} />
              <ReservationTable reservations={todayDay.reservations} groupByDate={false} />
            </>
          )}
        </section>
      )}

      {/* サイドバーと同じ行き先なので、カードにせず、罫線で区切った控えめな一覧にする */}
      <section className="space-y-3">
        <h2 className="text-lg font-bold">メニュー</h2>
        <ul className="grid border-t border-gray-300 sm:grid-cols-2 sm:gap-x-12">
          {MENU.map((item) => (
            <li key={item.href} className="border-b border-gray-200">
              <Link href={item.href} className="group block py-3">
                <span className="font-bold group-hover:underline group-hover:underline-offset-4">{item.label}</span>
                <span className="mt-0.5 block text-sm text-gray-500">{item.description}</span>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}

/**
 * 今日・今週・来週の件数。枠では囲まず、数字を並べて縦の罫線で区切る（スマートフォンでは横の罫線で縦に積む）。
 * 数字だけでは多いか少ないか分からないので、比べる相手や内訳を一言添える
 */
function Figures({ days, todayDay, monday }: { days: ScheduleDay[]; todayDay: ScheduleDay; monday: string }) {
  const thisWeek = countReservations(days.slice(0, 7));
  const nextWeek = countReservations(days.slice(7, 14));
  const todayCount = todayDay.reservations.length;
  const visited = todayDay.reservations.filter((reservation) => reservation.is_past).length;
  const diff = nextWeek - thisWeek;

  const figures = [
    {
      label: "今日の予約",
      value: todayCount,
      note: todayDay.is_closed && todayCount === 0 ? "本日は休診日" : `来院済み ${visited}、これから ${todayCount - visited}`,
    },
    {
      label: "今週の予約",
      value: thisWeek,
      note: `${formatMonthDay(monday)}〜${formatMonthDay(addDays(monday, 6))}`,
    },
    {
      label: "来週の予約",
      value: nextWeek,
      note: diff > 0 ? `今週より${diff}件多い` : diff < 0 ? `今週より${-diff}件少ない` : "今週と同じ",
    },
  ];

  return (
    <dl className="grid divide-y divide-gray-200 border-y border-gray-300 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
      {figures.map((figure) => (
        <div key={figure.label} className="py-3 sm:px-6 sm:first:pl-0">
          <dt className="text-xs text-gray-500">{figure.label}</dt>
          <dd className="text-3xl font-bold tabular-nums">
            {figure.value}
            <span className="ml-1 text-sm font-normal">件</span>
          </dd>
          <dd className="text-xs text-gray-500">{figure.note}</dd>
        </div>
      ))}
    </dl>
  );
}

/** 次の予約を1行の文で出す（時間軸や表を読まなくても、次に誰が来るか分かるように） */
function NextReservation({ reservations, nowMinutes }: { reservations: AdminReservation[]; nowMinutes: number }) {
  if (reservations.length === 0) return null;

  const next = reservations.find((reservation) => startMinutes(reservation) > nowMinutes);
  if (!next) {
    return <p className="text-sm text-gray-600">本日の予約は、すべて開始時刻を過ぎました。</p>;
  }

  return (
    <p className="text-sm">
      <span className="text-gray-500">次の予約</span>
      <span className="ml-3 font-bold">
        {formatTime(next.start_at.split(" ")[1])}　{next.patient_name}
      </span>
      （{VISIT_TYPE_LABELS[next.visit_type]}）{next.treatment_request && `　${next.treatment_request}`}
    </p>
  );
}
