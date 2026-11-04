"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";
import { alertBox, successBox } from "@/components/ui";
import { createReservation, type AdminReservation, type PatientInput } from "@/lib/adminApi";
import { ApiError } from "@/lib/api";
import { formatRange, slotEnd } from "./labels";
import PatientFields, { emptyPatient, toFieldErrors, type FieldErrors } from "./PatientFields";
import SlotPicker from "./SlotPicker";
import { adminPrimaryButton, adminSecondaryButton } from "./styles";
import { useAdminError } from "./useAdminError";

// 入力のまとまり（日時・患者情報・確認メール）。箱で囲まず、上の罫線で区切る
const section = "space-y-3 border-t border-gray-300 pt-5";

/** 新規登録の結果 */
type Created = { reservation: AdminReservation; mailSent: boolean; mailRequested: boolean };

/** 院長が予約を登録する画面（/admin/reservations/new）。お電話で受けた予約など */
export default function NewReservation() {
  const toErrorMessage = useAdminError();
  const [patient, setPatient] = useState<PatientInput>(emptyPatient);
  const [startAt, setStartAt] = useState<string | null>(null);
  const [sendMail, setSendMail] = useState(true);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [slotReloadKey, setSlotReloadKey] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [created, setCreated] = useState<Created | null>(null);

  const hasEmail = patient.patient_email.trim() !== "";

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;
    if (startAt === null) {
      setErrors({ start_at: "予約日時を選択してください。" });
      return;
    }

    setSubmitting(true);
    setErrors({});
    setMessage(null);
    try {
      const response = await createReservation({ ...patient, start_at: startAt, send_mail: sendMail && hasEmail });
      setCreated({ reservation: response.data, mailSent: response.mail_sent ?? false, mailRequested: sendMail && hasEmail });
      window.scrollTo({ top: 0 });
    } catch (e: unknown) {
      handleError(e);
    } finally {
      setSubmitting(false);
    }
  }

  function handleError(e: unknown) {
    if (e instanceof ApiError && e.status === 422) {
      const fieldErrors = toFieldErrors(e.fieldErrors);
      setErrors(fieldErrors);
      // 予約できない日時だった → 最新の ○／× を読み込み直す
      if (fieldErrors.start_at) {
        setStartAt(null);
        setSlotReloadKey((key) => key + 1);
      }
      return;
    }
    if (e instanceof ApiError && e.status === 409 && e.reason === "slot_taken") {
      setErrors({ start_at: e.message });
      setStartAt(null);
      setSlotReloadKey((key) => key + 1);
      return;
    }
    setMessage(toErrorMessage(e));
  }

  function startOver() {
    setPatient(emptyPatient);
    setStartAt(null);
    setSendMail(true);
    setCreated(null);
    setSlotReloadKey((key) => key + 1);
  }

  // ---- 登録が終わった ----
  if (created) {
    const { reservation, mailSent, mailRequested } = created;
    return (
      <div className="space-y-5">
        <h1 className="text-2xl font-bold">予約を登録しました</h1>
        <div className={successBox} role="status">
          <p className="text-base font-bold">
            {formatRange(reservation.start_at, reservation.end_at)}　{reservation.patient_name} 様
          </p>
          {mailRequested && mailSent && <p className="mt-1">確認メール（予約管理ページの URL 入り）を送信しました。</p>}
          {!mailRequested && <p className="mt-1">確認メールは送っていません。</p>}
        </div>
        {mailRequested && !mailSent && (
          <p className={alertBox} role="alert">
            確認メールの送信に失敗しました（予約は登録されています）。詳細画面から再送できます。
          </p>
        )}
        <div className="flex flex-wrap gap-2">
          <Link href={`/admin/reservations/${reservation.id}`} className={adminPrimaryButton}>
            この予約の詳細を見る
          </Link>
          <button type="button" className={adminSecondaryButton} onClick={startOver}>
            続けて登録する
          </button>
          <Link href="/admin/reservations" className={adminSecondaryButton}>
            予約一覧へ
          </Link>
        </div>
      </div>
    );
  }

  // ---- 入力 ----
  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-5">
      <h1 className="text-2xl font-bold">予約の新規登録</h1>

      {message && (
        <p className={alertBox} role="alert">
          {message}
        </p>
      )}

      <section className={section}>
        <h2 className="text-lg font-bold">予約日時</h2>
        <SlotPicker
          value={startAt}
          onChange={(value) => {
            setStartAt(value);
            setErrors((current) => ({ ...current, start_at: undefined }));
          }}
          reloadKey={slotReloadKey}
        />
        {startAt && <p className="text-sm font-bold text-brand-dark">選択中：{formatRange(startAt, slotEnd(startAt))}</p>}
        {errors.start_at && (
          <p className="text-sm text-red-700" role="alert">
            {errors.start_at}
          </p>
        )}
      </section>

      <section className={section}>
        <h2 className="text-lg font-bold">患者情報</h2>
        <PatientFields value={patient} errors={errors} onChange={setPatient} />
      </section>

      <section className={section}>
        <h2 className="text-lg font-bold">確認メール</h2>
        <label className={`flex items-center gap-2 ${hasEmail ? "" : "text-gray-400"}`}>
          <input
            type="checkbox"
            checked={sendMail && hasEmail}
            disabled={!hasEmail}
            onChange={(e) => setSendMail(e.target.checked)}
            className="h-4 w-4 accent-brand"
          />
          確認メールを送る
        </label>
        <p className="text-sm text-gray-600">
          {hasEmail
            ? "予約内容と、予約管理ページ（患者さんが確認・変更・キャンセルできるページ）の URL をお送りします。"
            : "メールアドレスを入力すると、確認メールを送れます。"}
        </p>
      </section>

      <button type="submit" className={adminPrimaryButton} disabled={submitting}>
        {submitting ? "登録中…" : "登録する"}
      </button>
    </form>
  );
}
