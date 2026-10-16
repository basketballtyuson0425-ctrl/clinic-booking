import type { Metadata } from "next";
import ManageReservation from "./_components/ManageReservation";

export const metadata: Metadata = {
  title: "ご予約の確認・変更",
  // URL に鍵が入っているので、検索エンジンに載せず、リンク先に URL を伝えない
  robots: { index: false, follow: false },
  referrer: "no-referrer",
};

/** 予約管理ページ（/reservation/{鍵}） */
export default async function ManageReservationPage({ params }: PageProps<"/reservation/[token]">) {
  const { token } = await params;

  return (
    <>
      <h1 className="mb-4 text-xl font-bold">ご予約の確認・変更</h1>
      <ManageReservation token={token} />
    </>
  );
}
