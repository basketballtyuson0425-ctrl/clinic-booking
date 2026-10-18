"use client";

import type { HoursImpact } from "@/lib/adminApi";
import { formatRange } from "./labels";
import { adminPrimaryButton, adminSecondaryButton } from "./styles";

type Props = {
  impact: HoursImpact;
  message: string; // 例：「この日には既に2件の予約があります。…変更しますか？」
  sendMail: boolean;
  submitting: boolean;
  onSendMailChange: (sendMail: boolean) => void;
  onConfirm: () => void;
  onBack: () => void;
};

/**
 * 営業時間を変える前の確認（要件25）。新しい営業時間から外れる予約を、名前と電話番号つきで見せる。
 * 予約は自動でキャンセル・変更しないので、院長が電話で連絡する
 */
export default function ImpactConfirm({ impact, message, sendMail, submitting, onSendMailChange, onConfirm, onBack }: Props) {
  const withEmail = impact.affected.filter((reservation) => reservation.has_email).length;

  return (
    <div className="space-y-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-4" role="alertdialog" aria-label="変更の確認">
      <p className="font-bold text-amber-900">{message}</p>

      {impact.affected.length === 0 ? (
        <p className="text-sm text-gray-700">新しい営業時間から外れる予約はありません。</p>
      ) : (
        <div className="space-y-2">
          <p className="text-sm font-bold text-red-700">
            新しい営業時間から外れる予約が {impact.affected.length} 件あります。変更後は「要連絡」と表示されます。患者さんにお電話で連絡してください。
          </p>
          <ul className="divide-y divide-amber-200 rounded-md border border-amber-200 bg-white text-sm">
            {impact.affected.map((reservation) => (
              <li key={reservation.id} className="flex flex-wrap gap-x-4 gap-y-1 px-3 py-2">
                <span className="font-bold whitespace-nowrap">{formatRange(reservation.start_at, reservation.end_at)}</span>
                <span>{reservation.patient_name} 様</span>
                <a href={`tel:${reservation.patient_phone}`} className="text-brand underline">
                  {reservation.patient_phone}
                </a>
                <span className="text-gray-600">{reservation.has_email ? "メールあり" : "メールなし"}</span>
              </li>
            ))}
          </ul>
          <label className={`flex items-center gap-2 text-sm ${withEmail > 0 ? "" : "text-gray-400"}`}>
            <input
              type="checkbox"
              checked={sendMail && withEmail > 0}
              disabled={withEmail === 0}
              onChange={(e) => onSendMailChange(e.target.checked)}
              className="h-4 w-4 accent-brand"
            />
            営業時間変更のお知らせメールを送る（メールアドレスがある {withEmail} 件）
          </label>
        </div>
      )}

      <div className="flex flex-wrap gap-2">
        <button type="button" className={adminPrimaryButton} onClick={onConfirm} disabled={submitting}>
          {submitting ? "保存中…" : "変更する"}
        </button>
        <button type="button" className={adminSecondaryButton} onClick={onBack} disabled={submitting}>
          戻る
        </button>
      </div>
    </div>
  );
}
