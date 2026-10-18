import type { Metadata } from "next";
import ReservationDetail from "../../../_components/ReservationDetail";

export const metadata: Metadata = {
  title: "予約の詳細",
};

/** 予約の詳細（/admin/reservations/{予約の ID}） */
export default async function ReservationDetailPage({ params }: PageProps<"/admin/reservations/[id]">) {
  const { id } = await params;

  // 別の予約を開いたら作り直す（前の予約の入力途中の内容を残さない）
  return <ReservationDetail key={id} id={id} />;
}
