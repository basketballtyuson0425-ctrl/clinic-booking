import type { Metadata } from "next";
import type { ReservationFilters } from "@/lib/adminApi";
import ReservationList from "../../_components/ReservationList";

export const metadata: Metadata = {
  title: "予約一覧",
};

/** URL の ?name=a&name=b のように同じ名前が複数あるときは、最初の値を使う */
function first(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value) ?? "";
}

/** 予約一覧（/admin/reservations?date=…&name=…） */
export default async function ReservationsPage({ searchParams }: PageProps<"/admin/reservations">) {
  const query = await searchParams;
  const status = first(query.status);
  const page = Number(first(query.page));

  const filters: ReservationFilters = {
    date: first(query.date),
    name: first(query.name),
    phone: first(query.phone),
    status: status === "reserved" || status === "cancelled" ? status : "",
    includePast: first(query.include_past) === "1",
    needsContact: first(query.needs_contact) === "1",
    page: Number.isInteger(page) && page > 1 ? page : 1,
  };

  // 条件が変わったら作り直して、入力欄も新しい条件に合わせる（ブラウザの「戻る」で戻ったときなど）
  return <ReservationList key={JSON.stringify(filters)} filters={filters} />;
}
