"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import { alertBox } from "@/components/ui";
import {
  fetchReservations,
  reservationFiltersToQuery,
  type AdminReservation,
  type Paginated,
  type ReservationFilters,
} from "@/lib/adminApi";
import Pagination from "./Pagination";
import ReservationTable from "./ReservationTable";
import { adminPrimaryButton, adminQuietButton, underlineInput } from "./styles";
import { useAdminError } from "./useAdminError";
import WeekCalendar from "./WeekCalendar";

export type ReservationView = "week" | "list";

const EMPTY_FILTERS: ReservationFilters = {
  date: "",
  name: "",
  phone: "",
  status: "",
  includePast: false,
  needsContact: false,
  page: 1,
};

/**
 * 予約一覧（/admin/reservations）。開いたときは週のカレンダー、「一覧」に切り替えると表を出す。
 * 絞り込みの条件は URL の ? 以降に入れる。詳細を開いてから「戻る」で戻っても、同じ条件の一覧が表示される
 */
export default function ReservationList({
  filters,
  view,
  week,
}: {
  filters: ReservationFilters;
  view: ReservationView;
  week: string | null;
}) {
  const router = useRouter();
  const toErrorMessage = useAdminError();
  const [result, setResult] = useState<Paginated<AdminReservation> | null>(null);
  const [error, setError] = useState<string | null>(null);
  // 入力中の条件（「検索」を押すまでは一覧に反映しない）
  const [draft, setDraft] = useState(filters);

  useEffect(() => {
    // 週のカレンダーは WeekCalendar が自分で読み込む
    if (view !== "list") return;
    let ignore = false;
    fetchReservations(filters)
      .then((response) => {
        if (!ignore) setResult(response);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [filters, view, toErrorMessage]);

  /** 一覧の URL。条件がないときも view=list を付ける（何も付けないと、週のカレンダーになるため） */
  function hrefFor(next: Partial<ReservationFilters>): string {
    const query = reservationFiltersToQuery(next);
    return `/admin/reservations?view=list${query === "" ? "" : `&${query}`}`;
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    // 条件を変えたら1ページ目から表示する。検索の結果は表で見るほうが読みやすいので、カレンダーからでも一覧に切り替える
    router.push(hrefFor({ ...draft, name: draft.name.trim(), phone: draft.phone.trim(), page: 1 }));
  }

  function handleClear() {
    if (view === "list") {
      router.push(hrefFor({}));
    } else {
      // カレンダーは条件で絞り込んでいないので、入力欄を空にするだけでよい
      setDraft(EMPTY_FILTERS);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <h1 className="text-2xl font-bold">予約一覧</h1>
        <nav aria-label="表示の切り替え" className="flex gap-5 text-sm">
          <ViewLink href="/admin/reservations" current={view === "week"}>
            カレンダー
          </ViewLink>
          <ViewLink href={hrefFor({})} current={view === "list"}>
            一覧
          </ViewLink>
        </nav>
      </div>

      {/* 絞り込みは画面の脇役なので、箱で囲まず、下線だけの入力欄を横に並べる */}
      <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-x-6 gap-y-4">
        <Filter id="filterDate" label="日付" className="w-full sm:w-40">
          <input
            id="filterDate"
            type="date"
            value={draft.date}
            onChange={(e) => setDraft({ ...draft, date: e.target.value })}
            className={underlineInput}
          />
        </Filter>
        <Filter id="filterName" label="氏名（一部でも可）" className="w-full sm:w-44">
          <input
            id="filterName"
            type="search"
            value={draft.name}
            onChange={(e) => setDraft({ ...draft, name: e.target.value })}
            className={underlineInput}
          />
        </Filter>
        <Filter id="filterPhone" label="電話番号（一部でも可）" className="w-full sm:w-40">
          <input
            id="filterPhone"
            type="search"
            inputMode="tel"
            value={draft.phone}
            onChange={(e) => setDraft({ ...draft, phone: e.target.value })}
            className={underlineInput}
          />
        </Filter>
        <Filter id="filterStatus" label="状態" className="w-full sm:w-36">
          <select
            id="filterStatus"
            value={draft.status}
            onChange={(e) => setDraft({ ...draft, status: e.target.value as ReservationFilters["status"] })}
            className={underlineInput}
          >
            <option value="">すべて</option>
            <option value="reserved">予約中</option>
            <option value="visited">来院済み</option>
            <option value="cancelled">キャンセル済み</option>
          </select>
        </Filter>
        <div className="flex flex-wrap gap-x-5 gap-y-1">
          <label className="flex min-h-10 items-center gap-2 text-sm text-gray-700">
            <input
              type="checkbox"
              checked={draft.needsContact}
              onChange={(e) => setDraft({ ...draft, needsContact: e.target.checked })}
              className="h-4 w-4 accent-brand"
            />
            要連絡だけ
          </label>
          <label className="flex min-h-10 items-center gap-2 text-sm text-gray-700">
            <input
              type="checkbox"
              checked={draft.includePast}
              onChange={(e) => setDraft({ ...draft, includePast: e.target.checked })}
              className="h-4 w-4 accent-brand"
            />
            過去の予約も表示
          </label>
        </div>
        <div className="flex w-full items-center justify-between gap-4 sm:ml-auto sm:w-auto">
          <button type="button" className={adminQuietButton} onClick={handleClear}>
            条件をクリア
          </button>
          <button type="submit" className={adminPrimaryButton}>
            検索
          </button>
        </div>
      </form>

      {view === "week" && <WeekCalendar week={week} />}

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}
      {view === "list" && !error && result === null && <p className="py-6 text-center text-gray-500">読み込み中…</p>}

      {result && (
        <>
          <p className="text-xs text-gray-500">
            {result.meta.total} 件
            {result.meta.total > 0 && `（${result.meta.from}〜${result.meta.to} 件目を表示）`}
            {/* 日付を指定したとき・来院済みで絞ったときは、過去の予約も表示する（「過去の予約も表示」は関係しない） */}
            {!filters.date && !filters.includePast && filters.status !== "visited" && "　今日以降の予約を表示しています"}
          </p>
          <ReservationTable reservations={result.data} />
          <Pagination
            page={result.meta.current_page}
            lastPage={result.meta.last_page}
            hrefFor={(page) => hrefFor({ ...filters, page })}
          />
        </>
      )}
    </div>
  );
}

/** 「カレンダー｜一覧」の切り替え。いま表示している方は太字と下線 */
function ViewLink({ href, current, children }: { href: string; current: boolean; children: ReactNode }) {
  return (
    <Link
      href={href}
      aria-current={current ? "page" : undefined}
      className={
        current
          ? "font-bold underline decoration-2 underline-offset-8"
          : "text-gray-600 underline-offset-8 hover:text-gray-900 hover:underline"
      }
    >
      {children}
    </Link>
  );
}

/** 絞り込みの1項目（小さな見出し＋入力欄） */
function Filter({ id, label, className, children }: { id: string; label: string; className: string; children: ReactNode }) {
  return (
    <div className={className}>
      <label htmlFor={id} className="block text-xs text-gray-500">
        {label}
      </label>
      {children}
    </div>
  );
}
