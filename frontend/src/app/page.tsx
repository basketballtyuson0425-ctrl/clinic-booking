import { redirect } from "next/navigation";

// トップページは使わないので、予約画面へ移動する
export default function Home() {
  redirect("/reserve");
}
