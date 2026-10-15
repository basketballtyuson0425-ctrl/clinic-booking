"use client";

import { useState, type FormEvent, type ReactNode } from "react";
import { formatDateTime } from "@/lib/date";
import { validateForm, type FormErrors, type ReservationForm } from "./form";
import { primaryButton, secondaryButton } from "./ui";

type Props = {
  startAt: string; // "2026-10-20 09:00"
  form: ReservationForm;
  initialErrors: FormErrors; // サーバーから返ってきたエラー（あれば）
  onChange: (form: ReservationForm) => void;
  onNext: () => void;
  onBack: () => void;
};

const inputClass =
  "block w-full rounded-md border border-gray-300 bg-white px-3 py-3 text-base focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-light";

/** ③ お客様情報を入力する */
export default function InfoStep({ startAt, form, initialErrors, onChange, onNext, onBack }: Props) {
  const [errors, setErrors] = useState<FormErrors>(initialErrors);

  // 入力したら、その欄のエラー表示を消す
  function update<K extends keyof ReservationForm>(key: K, value: ReservationForm[K]) {
    onChange({ ...form, [key]: value });
    setErrors((current) => ({ ...current, [key]: undefined }));
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); // ブラウザ標準の送信（ページの再読み込み）を止める
    const result = validateForm(form);
    setErrors(result);
    if (Object.keys(result).length === 0) {
      onNext();
    }
  }

  const hasEmail = form.patientEmail.trim() !== "";

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-5">
      <div>
        <h2 className="text-lg font-bold">お客様情報を入力してください</h2>
        <p className="mt-1 text-sm text-gray-600">ご予約日時：{formatDateTime(startAt)}</p>
      </div>

      <Field id="patientName" label="氏名" required error={errors.patientName}>
        <input
          id="patientName"
          type="text"
          autoComplete="name"
          placeholder="例：山田 太郎"
          value={form.patientName}
          onChange={(e) => update("patientName", e.target.value)}
          aria-invalid={!!errors.patientName}
          aria-describedby={errors.patientName ? "patientName-error" : undefined}
          className={inputClass}
        />
      </Field>

      <Field
        id="patientPhone"
        label="電話番号"
        required
        error={errors.patientPhone}
        hint="ご予約について、院からお電話することがあります。"
      >
        <input
          id="patientPhone"
          type="tel"
          inputMode="tel"
          autoComplete="tel"
          placeholder="例：090-1234-5678"
          value={form.patientPhone}
          onChange={(e) => update("patientPhone", e.target.value)}
          aria-invalid={!!errors.patientPhone}
          aria-describedby={errors.patientPhone ? "patientPhone-error" : "patientPhone-hint"}
          className={inputClass}
        />
      </Field>

      <fieldset>
        <legend className="mb-2 font-bold">
          初回／再来 <RequiredBadge />
        </legend>
        <div className="grid grid-cols-2 gap-2">
          {(
            [
              ["first", "初めて"],
              ["return", "2回目以降"],
            ] as const
          ).map(([value, label]) => (
            <label
              key={value}
              className={`flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-lg border text-base ${
                form.visitType === value ? "border-brand bg-brand-light font-bold text-brand-dark" : "border-gray-300 bg-white"
              }`}
            >
              <input
                type="radio"
                name="visitType"
                value={value}
                checked={form.visitType === value}
                onChange={() => update("visitType", value)}
                className="accent-brand"
              />
              {label}
            </label>
          ))}
        </div>
        {errors.visitType && <ErrorText id="visitType-error">{errors.visitType}</ErrorText>}
      </fieldset>

      <Field
        id="patientEmail"
        label="メールアドレス"
        error={errors.patientEmail}
        hint="予約日時の確認や変更・キャンセルのお知らせをメールで受け取りたい場合は、メールアドレスを入力してください。"
      >
        <input
          id="patientEmail"
          type="email"
          inputMode="email"
          autoComplete="email"
          placeholder="例：yamada@example.com"
          value={form.patientEmail}
          onChange={(e) => update("patientEmail", e.target.value)}
          aria-invalid={!!errors.patientEmail}
          aria-describedby={errors.patientEmail ? "patientEmail-error" : "patientEmail-hint"}
          className={inputClass}
        />
      </Field>

      {/* メールアドレスを入力したときだけ、確認用の欄を表示する */}
      {hasEmail && (
        <Field
          id="patientEmailConfirmation"
          label="メールアドレス（確認）"
          required
          error={errors.patientEmailConfirmation}
          hint="確認のため、もう一度入力してください。"
        >
          <input
            id="patientEmailConfirmation"
            type="email"
            inputMode="email"
            autoComplete="off"
            value={form.patientEmailConfirmation}
            onChange={(e) => update("patientEmailConfirmation", e.target.value)}
            aria-invalid={!!errors.patientEmailConfirmation}
            aria-describedby={
              errors.patientEmailConfirmation ? "patientEmailConfirmation-error" : "patientEmailConfirmation-hint"
            }
            className={inputClass}
          />
        </Field>
      )}

      <Field id="treatmentRequest" label="施術希望内容" error={errors.treatmentRequest}>
        <textarea
          id="treatmentRequest"
          rows={3}
          placeholder="例：腰痛、肩こり など"
          value={form.treatmentRequest}
          onChange={(e) => update("treatmentRequest", e.target.value)}
          aria-invalid={!!errors.treatmentRequest}
          aria-describedby={errors.treatmentRequest ? "treatmentRequest-error" : undefined}
          className={inputClass}
        />
      </Field>

      <div className="space-y-3 pt-2">
        <button type="submit" className={primaryButton}>
          確認画面へ進む
        </button>
        <button type="button" className={secondaryButton} onClick={onBack}>
          時間を選び直す
        </button>
      </div>
    </form>
  );
}

function RequiredBadge() {
  return <span className="ml-1 rounded bg-red-600 px-1.5 py-0.5 text-xs font-normal text-white">必須</span>;
}

function OptionalBadge() {
  return <span className="ml-1 rounded bg-gray-400 px-1.5 py-0.5 text-xs font-normal text-white">任意</span>;
}

function ErrorText({ id, children }: { id: string; children: ReactNode }) {
  return (
    <p id={id} className="mt-1 text-sm text-red-700" role="alert">
      {children}
    </p>
  );
}

/** ラベル・入力欄・補足説明・エラーをまとめた1項目 */
function Field({
  id,
  label,
  required = false,
  hint,
  error,
  children,
}: {
  id: string;
  label: string;
  required?: boolean;
  hint?: string;
  error?: string;
  children: ReactNode;
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-2 block font-bold">
        {label}
        {required ? <RequiredBadge /> : <OptionalBadge />}
      </label>
      {children}
      {hint && (
        <p id={`${id}-hint`} className="mt-1 text-sm text-gray-600">
          {hint}
        </p>
      )}
      {error && <ErrorText id={`${id}-error`}>{error}</ErrorText>}
    </div>
  );
}
