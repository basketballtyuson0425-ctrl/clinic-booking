"use client";

import type { ReactNode } from "react";
import type { AdminReservation, PatientInput } from "@/lib/adminApi";
import { textInput } from "./styles";

/** サーバーから返ってきた項目ごとのエラー（例：{ patient_phone: "電話番号の桁数が正しくありません。" }） */
export type FieldErrors = Partial<Record<keyof PatientInput | "start_at", string>>;

export const emptyPatient: PatientInput = {
  patient_name: "",
  patient_phone: "",
  patient_email: "",
  visit_type: "",
  treatment_request: "",
};

/** 予約の今の患者情報を、入力欄の初期値にする */
export function patientFromReservation(reservation: AdminReservation): PatientInput {
  return {
    patient_name: reservation.patient_name,
    patient_phone: reservation.patient_phone,
    patient_email: reservation.patient_email ?? "",
    visit_type: reservation.visit_type,
    treatment_request: reservation.treatment_request ?? "",
  };
}

/** 422 の項目ごとのエラーを、1項目1つの文章にする */
export function toFieldErrors(fieldErrors: Record<string, string[]>): FieldErrors {
  return Object.fromEntries(Object.entries(fieldErrors).map(([key, messages]) => [key, messages[0]]));
}

type Props = {
  value: PatientInput;
  errors: FieldErrors;
  onChange: (value: PatientInput) => void;
};

/**
 * 患者情報の入力欄（新規登録と修正で共通）。
 * 入力の細かいチェック（電話番号の桁数など）はサーバーが行い、結果を各欄の下に表示する
 */
export default function PatientFields({ value, errors, onChange }: Props) {
  function update<K extends keyof PatientInput>(key: K, next: PatientInput[K]) {
    onChange({ ...value, [key]: next });
  }

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Field id="patientName" label="氏名" required error={errors.patient_name}>
        <input
          id="patientName"
          type="text"
          autoComplete="off"
          value={value.patient_name}
          onChange={(e) => update("patient_name", e.target.value)}
          aria-invalid={!!errors.patient_name}
          className={textInput}
        />
      </Field>

      <Field id="patientPhone" label="電話番号" required error={errors.patient_phone}>
        <input
          id="patientPhone"
          type="tel"
          inputMode="tel"
          autoComplete="off"
          placeholder="例：090-1234-5678"
          value={value.patient_phone}
          onChange={(e) => update("patient_phone", e.target.value)}
          aria-invalid={!!errors.patient_phone}
          className={textInput}
        />
      </Field>

      <Field id="patientEmail" label="メールアドレス" error={errors.patient_email}>
        <input
          id="patientEmail"
          type="email"
          inputMode="email"
          autoComplete="off"
          value={value.patient_email}
          onChange={(e) => update("patient_email", e.target.value)}
          aria-invalid={!!errors.patient_email}
          className={textInput}
        />
      </Field>

      <fieldset>
        <legend className="mb-1 text-sm font-bold">
          初回／再来 <RequiredMark />
        </legend>
        <div className="flex gap-4 py-2">
          {(
            [
              ["first", "初回"],
              ["return", "再来"],
            ] as const
          ).map(([type, label]) => (
            <label key={type} className="flex items-center gap-2 text-base">
              <input
                type="radio"
                name="visitType"
                value={type}
                checked={value.visit_type === type}
                onChange={() => update("visit_type", type)}
                className="h-4 w-4 accent-brand"
              />
              {label}
            </label>
          ))}
        </div>
        {errors.visit_type && <ErrorText>{errors.visit_type}</ErrorText>}
      </fieldset>

      <div className="md:col-span-2">
        <Field id="treatmentRequest" label="施術希望内容" error={errors.treatment_request}>
          <textarea
            id="treatmentRequest"
            rows={3}
            value={value.treatment_request}
            onChange={(e) => update("treatment_request", e.target.value)}
            aria-invalid={!!errors.treatment_request}
            className={textInput}
          />
        </Field>
      </div>
    </div>
  );
}

function RequiredMark() {
  return <span className="ml-1 text-xs font-normal text-red-700">（必須）</span>;
}

function ErrorText({ children }: { children: ReactNode }) {
  return (
    <p className="mt-1 text-sm text-red-700" role="alert">
      {children}
    </p>
  );
}

function Field({
  id,
  label,
  required = false,
  error,
  children,
}: {
  id: string;
  label: string;
  required?: boolean;
  error?: string;
  children: ReactNode;
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-bold">
        {label}
        {required && <RequiredMark />}
      </label>
      {children}
      {error && <ErrorText>{error}</ErrorText>}
    </div>
  );
}
