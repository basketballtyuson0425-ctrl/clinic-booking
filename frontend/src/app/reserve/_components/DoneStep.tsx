import Link from "next/link";
import type { ReservationCreated } from "@/lib/api";
import { clinic } from "@/lib/clinic";
import { formatDateTime } from "@/lib/date";
import { primaryButton } from "./ui";

type Props = {
  result: ReservationCreated;
};

/** ⑤ 予約完了 */
export default function DoneStep({ result }: Props) {
  const manageUrl = `/reservation/${result.manage_token}`;

  return (
    <div className="space-y-5">
      <h2 className="text-center text-xl font-bold text-brand-dark">予約が完了しました</h2>

      <dl className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
        <div className="px-4 py-3">
          <dt className="text-sm text-gray-600">ご予約日時</dt>
          <dd className="mt-1 text-lg font-bold">{formatDateTime(result.start_at)}</dd>
        </div>
        <div className="px-4 py-3">
          <dt className="text-sm text-gray-600">氏名</dt>
          <dd className="mt-1 text-base">{result.patient_name} 様</dd>
        </div>
      </dl>

      {result.has_email && (
        <p className="rounded-lg bg-brand-light px-4 py-3 text-sm text-brand-dark">
          ご入力のメールアドレスに確認メールを送信しました。
        </p>
      )}

      <div className="space-y-2">
        <Link href={manageUrl} className={primaryButton}>
          予約内容を確認する（予約管理ページ）
        </Link>
        <p className="text-sm text-gray-600">
          上のボタンから予約管理ページを開き、そのページをブックマークなどで保存してください。この画面を閉じると、予約管理ページの
          URL は表示できなくなります。
          {result.has_email && "確認メールにも同じ URL を記載しています。"}
        </p>
      </div>

      {result.has_email ? (
        <p className="text-sm text-gray-700">
          ご予約の変更・キャンセルは、予約時刻の1時間前まで予約管理ページから行えます。
        </p>
      ) : (
        <p className="text-sm text-gray-700">
          ご予約の変更・キャンセルは、お電話でお願いします。
          <br />
          電話：
          <a href={`tel:${clinic.phone}`} className="font-bold text-brand underline">
            {clinic.phone}
          </a>
        </p>
      )}
    </div>
  );
}
