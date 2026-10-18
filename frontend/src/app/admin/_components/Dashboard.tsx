"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { alertBox } from "@/components/ui";
import { fetchReservations, type AdminReservation } from "@/lib/adminApi";
import { formatDate, todayString } from "@/lib/date";
import ReservationTable from "./ReservationTable";
import { panel } from "./styles";
import { useAdminError } from "./useAdminError";

const MENU = [
  { href: "/admin/reservations", label: "予約一覧", description: "予約の確認・検索、日時の変更・キャンセル" },
  { href: "/admin/reservations/new", label: "新規登録", description: "お電話などで受けた予約を登録します" },
  { href: "/admin/calendar", label: "営業日カレンダー", description: "日ごと・曜日ごとの営業時間の変更" },
  { href: "/admin/mail-logs", label: "メール送信記録", description: "患者さんに送ったメールと、送信の失敗" },
];

/** 管理画面のトップ（/admin）。本日の予約と、各画面へのメニュー */
export default function Dashboard() {
  const toErrorMessage = useAdminError();
  const [today] = useState(todayString);
  const [reservations, setReservations] = useState<AdminReservation[] | null>(null);
  const [needsContact, setNeedsContact] = useState<AdminReservation[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let ignore = false;
    // 本日の予約と、要連絡の予約（営業時間の変更で、営業時間外になった予約）を同時に読み込む
    Promise.all([fetchReservations({ date: today }), fetchReservations({ needsContact: true })])
      .then(([todays, contacts]) => {
        if (ignore) return;
        setReservations(todays.data);
        setNeedsContact(contacts.data);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [today, toErrorMessage]);

  const activeCount = reservations?.filter((reservation) => reservation.status === "reserved").length ?? 0;

  return (
    <div className="space-y-8">
      {needsContact.length > 0 && (
        <section className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-4">
          <h2 className="text-lg font-bold text-amber-900">要連絡の予約（{needsContact.length} 件）</h2>
          <p className="text-sm text-amber-900">
            営業時間の変更で、営業時間外になった予約です。患者さんにお電話で連絡し、日時を変更するか、キャンセルしてください。
          </p>
          <ReservationTable reservations={needsContact} />
        </section>
      )}

      <section className="space-y-3">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h1 className="text-xl font-bold">本日の予約（{formatDate(today)}）</h1>
          {reservations && <p className="text-sm text-gray-600">予約中 {activeCount} 件（キャンセル済みを除く）</p>}
        </div>
        {error && (
          <p className={alertBox} role="alert">
            {error}
          </p>
        )}
        {!error && reservations === null && <p className="py-6 text-center text-gray-500">読み込み中…</p>}
        {reservations && <ReservationTable reservations={reservations} />}
      </section>

      <section className="space-y-3">
        <h2 className="text-lg font-bold">メニュー</h2>
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {MENU.map((item) => (
            <li key={item.href}>
              <Link href={item.href} className={`${panel} block h-full px-4 py-4 hover:border-brand hover:bg-brand-light`}>
                <span className="font-bold text-brand-dark">{item.label}</span>
                <span className="mt-1 block text-sm text-gray-600">{item.description}</span>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
