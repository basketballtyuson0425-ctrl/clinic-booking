"use client";

import { formatDateTime } from "@/lib/date";
import { normalizeEmail, normalizePhone, type ReservationForm } from "./form";
import { alertBox, primaryButton, secondaryButton } from "./ui";

type Props = {
  startAt: string;
  form: ReservationForm;
  submitting: boolean; // 送信中か（送信中はボタンを押せなくする）
  error: string | null;
  onSubmit: () => void;
  onBack: () => void;
};

/** ④ 入力内容を確認して、予約を確定する */
export default function ConfirmStep({ startAt, form, submitting, error, onSubmit, onBack }: Props) {
  const email = normalizeEmail(form.patientEmail);

  const rows: [string, string][] = [
    ["ご予約日時", formatDateTime(startAt)],
    ["氏名", form.patientName.trim()],
    ["電話番号", normalizePhone(form.patientPhone)],
    ["初回／再来", form.visitType === "first" ? "初めて" : "2回目以降"],
    ["メールアドレス", email === "" ? "（入力なし）" : email],
    ["施術希望内容", form.treatmentRequest.trim() === "" ? "（入力なし）" : form.treatmentRequest.trim()],
  ];

  return (
    <div className="space-y-5">
      <h2 className="text-lg font-bold">入力内容をご確認ください</h2>

      <dl className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
        {rows.map(([label, value]) => (
          <div key={label} className="px-4 py-3">
            <dt className="text-sm text-gray-600">{label}</dt>
            {/* 改行を含む施術希望内容も、そのまま表示する */}
            <dd className="mt-1 whitespace-pre-wrap break-words text-base">{value}</dd>
          </div>
        ))}
      </dl>

      {email === "" && (
        <p className="rounded-lg bg-brand-light px-4 py-3 text-sm text-brand-dark">
          メールアドレスの入力がないため、確認メールは届きません。ご予約の変更・キャンセルはお電話でお願いします。
        </p>
      )}

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}

      <div className="space-y-3">
        <button type="button" className={primaryButton} onClick={onSubmit} disabled={submitting}>
          {submitting ? "送信中…" : "この内容で予約する"}
        </button>
        <button type="button" className={secondaryButton} onClick={onBack} disabled={submitting}>
          入力内容を修正する
        </button>
      </div>
    </div>
  );
}
