import Link from "next/link";
import { primaryButton } from "@/components/ui";
import { clinic } from "@/lib/clinic";

/** 予約管理ページの URL が無効・期限切れのとき */
export default function InvalidLink() {
  return (
    <div className="space-y-5">
      <div className="rounded-lg border border-gray-200 bg-white px-4 py-5">
        <h2 className="text-lg font-bold">このページは無効か、有効期限が切れています</h2>
        <p className="mt-2 text-sm text-gray-700">
          ご予約の時間を過ぎた場合や、URL が正しくない場合に表示されます。メールに記載の URL を、もう一度ご確認ください。
        </p>
      </div>

      <Link href="/reserve" className={primaryButton}>
        新しく予約する
      </Link>

      <p className="text-sm text-gray-700">
        ご不明な点は、お電話でお問い合わせください。
        <br />
        電話：
        <a href={`tel:${clinic.phone}`} className="font-bold text-brand underline">
          {clinic.phone}
        </a>
      </p>
    </div>
  );
}
