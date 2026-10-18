"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
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
import { adminPrimaryButton, adminSecondaryButton, panel, textInput } from "./styles";
import { useAdminError } from "./useAdminError";

/**
 * 予約一覧（/admin/reservations）。
 * 絞り込みの条件は URL の ? 以降に入れる。詳細を開いてから「戻る」で戻っても、同じ条件の一覧が表示される
 */
export default function ReservationList({ filters }: { filters: ReservationFilters }) {
  const router = useRouter();
  const toErrorMessage = useAdminError();
  const [result, setResult] = useState<Paginated<AdminReservation> | null>(null);
  const [error, setError] = useState<string | null>(null);
  // 入力中の条件（「検索」を押すまでは一覧に反映しない）
  const [draft, setDraft] = useState(filters);

  useEffect(() => {
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
  }, [filters, toErrorMessage]);

  function hrefFor(next: Partial<ReservationFilters>): string {
    const query = reservationFiltersToQuery(next);
    return query === "" ? "/admin/reservations" : `/admin/reservations?${query}`;
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    // 条件を変えたら1ページ目から表示する
    router.push(hrefFor({ ...draft, name: draft.name.trim(), phone: draft.phone.trim(), page: 1 }));
  }

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">予約一覧</h1>

      <form onSubmit={handleSubmit} className={`${panel} grid gap-3 px-4 py-4 sm:grid-cols-2 lg:grid-cols-4`}>
        <div>
          <label htmlFor="filterDate" className="mb-1 block text-sm font-bold">
            日付
          </label>
          <input
            id="filterDate"
            type="date"
            value={draft.date}
            onChange={(e) => setDraft({ ...draft, date: e.target.value })}
            className={textInput}
          />
        </div>
        <div>
          <label htmlFor="filterName" className="mb-1 block text-sm font-bold">
            氏名（一部でも可）
          </label>
          <input
            id="filterName"
            type="search"
            value={draft.name}
            onChange={(e) => setDraft({ ...draft, name: e.target.value })}
            className={textInput}
          />
        </div>
        <div>
          <label htmlFor="filterPhone" className="mb-1 block text-sm font-bold">
            電話番号（一部でも可）
          </label>
          <input
            id="filterPhone"
            type="search"
            inputMode="tel"
            value={draft.phone}
            onChange={(e) => setDraft({ ...draft, phone: e.target.value })}
            className={textInput}
          />
        </div>
        <div>
          <label htmlFor="filterStatus" className="mb-1 block text-sm font-bold">
            状態
          </label>
          <select
            id="filterStatus"
            value={draft.status}
            onChange={(e) => setDraft({ ...draft, status: e.target.value as ReservationFilters["status"] })}
            className={textInput}
          >
            <option value="">すべて</option>
            <option value="reserved">予約中</option>
            <option value="cancelled">キャンセル済み</option>
          </select>
        </div>
        <div className="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-4">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={draft.includePast}
              onChange={(e) => setDraft({ ...draft, includePast: e.target.checked })}
              className="h-4 w-4 accent-brand"
            />
            過去の予約も表示（日付を指定したときは、その日の予約を表示します）
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={draft.needsContact}
              onChange={(e) => setDraft({ ...draft, needsContact: e.target.checked })}
              className="h-4 w-4 accent-brand"
            />
            要連絡の予約だけ
          </label>
          <div className="ml-auto flex gap-2">
            <button type="button" className={adminSecondaryButton} onClick={() => router.push("/admin/reservations")}>
              条件をクリア
            </button>
            <button type="submit" className={adminPrimaryButton}>
              検索
            </button>
          </div>
        </div>
      </form>

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}
      {!error && result === null && <p className="py-6 text-center text-gray-500">読み込み中…</p>}

      {result && (
        <>
          <p className="text-sm text-gray-600">
            {result.meta.total} 件
            {result.meta.total > 0 && `（${result.meta.from}〜${result.meta.to} 件目を表示）`}
            {!filters.date && !filters.includePast && "　※ 今日以降の予約を表示しています"}
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
