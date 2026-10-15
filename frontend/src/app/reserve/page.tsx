import type { Metadata } from "next";
import ReservationFlow from "./_components/ReservationFlow";

export const metadata: Metadata = {
  title: "ネット予約",
};

/** 患者側の予約画面（/reserve） */
export default function ReservePage() {
  return (
    <>
      <h1 className="mb-4 text-xl font-bold">ネット予約</h1>
      <ReservationFlow />
    </>
  );
}
