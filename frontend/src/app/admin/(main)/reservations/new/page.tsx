import type { Metadata } from "next";
import NewReservation from "../../../_components/NewReservation";

export const metadata: Metadata = {
  title: "予約の新規登録",
};

/** 院長が予約を登録する画面（/admin/reservations/new） */
export default function NewReservationPage() {
  return <NewReservation />;
}
