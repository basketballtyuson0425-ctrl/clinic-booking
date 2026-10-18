"use client";

import Link from "next/link";
import { useEffect, useState, type ReactNode } from "react";
import { alertBox, successBox } from "@/components/ui";
import {
  cancelReservation,
  fetchReservation,
  resendReservationMail,
  updateReservation,
  type AdminReservation,
  type PatientInput,
  type ReservationResponse,
} from "@/lib/adminApi";
import { ApiError } from "@/lib/api";
import { isFuture } from "@/lib/date";
import {
  formatRange,
  MAIL_TYPE_LABELS,
  MailResultBadge,
  NeedsContactBadge,
  slotEnd,
  StatusBadge,
  VISIT_TYPE_LABELS,
} from "./labels";
import PatientFields, { patientFromReservation, toFieldErrors, type FieldErrors } from "./PatientFields";
import SlotPicker from "./SlotPicker";
import { adminDangerButton, adminPrimaryButton, adminSecondaryButton, panel } from "./styles";
import { useAdminError } from "./useAdminError";

// 画面の状態：表示中／日時の変更／患者情報の修正／キャンセルの確認／再送の確認
type Mode = "view" | "time" | "info" | "cancel" | "resend";

/** 予約の詳細（/admin/reservations/[id]）。日時の変更・患者情報の修正・キャンセル・メールの再送を行う */
export default function ReservationDetail({ id }: { id: string }) {
  const toErrorMessage = useAdminError();
  const [reservation, setReservation] = useState<AdminReservation | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [mode, setMode] = useState<Mode>("view");
  const [newStartAt, setNewStartAt] = useState<string | null>(null);
  const [patientDraft, setPatientDraft] = useState<PatientInput | null>(null);
  const [sendMail, setSendMail] = useState(true);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [slotReloadKey, setSlotReloadKey] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [doneMessage, setDoneMessage] = useState<string | null>(null);
  const [mailFailed, setMailFailed] = useState(false);

  useEffect(() => {
    let ignore = false;
    fetchReservation(id)
      .then((response) => {
        if (!ignore) setReservation(response.data);
      })
      .catch((e: unknown) => {
        if (!ignore) setLoadError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [id, toErrorMessage]);

  if (loadError) {
    return (
      <div className="space-y-4">
        <p className={alertBox} role="alert">
          {loadError}
        </p>
        <Link href="/admin/reservations" className={adminSecondaryButton}>
          予約一覧へ戻る
        </Link>
      </div>
    );
  }

  if (reservation === null) {
    return <p className="py-10 text-center text-gray-500">読み込み中…</p>;
  }

  // この下の関数の中でも「null ではない」と TypeScript に分かるよう、別の名前の定数に入れておく
  const current = reservation;
  const hasEmail = current.patient_email !== null;
  // 日時の変更・キャンセル・再送ができるのは、予約中で予約時刻の前だけ（最終的な判定はサーバーが行う）
  const canOperate = reservation.status === "reserved" && isFuture(reservation.start_at);
  const currentLabel = formatRange(reservation.start_at, reservation.end_at);

  function goTo(next: Mode) {
    setMode(next);
    setActionError(null);
    setFieldErrors({});
    window.scrollTo({ top: 0 });
  }

  function startAction(next: Mode) {
    setDoneMessage(null);
    setMailFailed(false);
    setSendMail(true);
    setNewStartAt(null);
    setPatientDraft(patientFromReservation(current));
    goTo(next);
  }

  /**
   * 操作が成功したら、最新の予約と完了メッセージを表示する
   *
   * @param mailRequested メールを送るよう指定したか（送れなかったときに警告を出すため）
   */
  function showResult(response: ReservationResponse, doneText: string, mailRequested: boolean, mailText: string) {
    setReservation(response.data);
    const sent = response.mail_sent ?? false;
    setDoneMessage(`${doneText}${sent ? mailText : ""}`);
    setMailFailed(mailRequested && !sent);
    goTo("view");
  }

  /** 送信中はボタンを押せなくし、エラーのときは理由に合わせて表示する */
  async function submit(action: () => Promise<void>) {
    if (submitting) return;
    setSubmitting(true);
    setActionError(null);
    setFieldErrors({});
    try {
      await action();
    } catch (e: unknown) {
      handleError(e);
    } finally {
      setSubmitting(false);
    }
  }

  function handleError(e: unknown) {
    if (e instanceof ApiError && e.status === 422 && Object.keys(e.fieldErrors).length > 0) {
      const errors = toFieldErrors(e.fieldErrors);
      setFieldErrors(errors);
      if (errors.start_at) {
        setNewStartAt(null);
        setSlotReloadKey((key) => key + 1);
      }
      return;
    }
    // 選んだ枠が直前に埋まった → 最新の ○／× を読み込み直す
    if (e instanceof ApiError && e.status === 409 && e.reason === "slot_taken") {
      setFieldErrors({ start_at: e.message });
      setNewStartAt(null);
      setSlotReloadKey((key) => key + 1);
      return;
    }
    // キャンセル済み・予約時刻を過ぎたなどで操作できなくなった → 最新の状態を読み込み直して表示する
    if (e instanceof ApiError && e.status === 403) {
      setMode("view");
      setActionError(e.message);
      fetchReservation(id)
        .then((response) => setReservation(response.data))
        .catch(() => {});
      return;
    }
    setActionError(toErrorMessage(e));
  }

  function changeTime() {
    if (newStartAt === null) {
      setFieldErrors({ start_at: "新しい日時を選択してください。" });
      return;
    }
    const mailRequested = sendMail && hasEmail;
    return submit(async () => {
      const response = await updateReservation(current.id, { start_at: newStartAt, send_mail: mailRequested });
      showResult(response, "予約日時を変更しました。", mailRequested, "お知らせメールを送信しました。");
    });
  }

  function changeInfo() {
    if (patientDraft === null) return;
    return submit(async () => {
      const response = await updateReservation(current.id, patientDraft);
      showResult(response, response.changed ? "患者情報を修正しました。" : "変更はありませんでした。", false, "");
    });
  }

  function cancel() {
    const mailRequested = sendMail && hasEmail;
    return submit(async () => {
      const response = await cancelReservation(current.id, mailRequested);
      showResult(response, "予約をキャンセルしました。", mailRequested, "お知らせメールを送信しました。");
    });
  }

  function resend() {
    return submit(async () => {
      const response = await resendReservationMail(current.id);
      if (response.mail_sent) {
        showResult(response, "予約内容のお知らせを送信しました。", true, "");
      } else {
        setReservation(response.data);
        setDoneMessage(null);
        setMailFailed(false);
        goTo("view");
        setActionError("メールの送信に失敗しました。以前お送りした予約管理ページの URL は、そのまま使えます。");
      }
    });
  }

  const errorBox = actionError && (
    <p className={alertBox} role="alert">
      {actionError}
    </p>
  );
  const backButton = (
    <button type="button" className={adminSecondaryButton} onClick={() => goTo("view")} disabled={submitting}>
      やめる
    </button>
  );
  const mailCheckbox = (label: string) => (
    <label className={`flex items-center gap-2 ${hasEmail ? "" : "text-gray-400"}`}>
      <input
        type="checkbox"
        checked={sendMail && hasEmail}
        disabled={!hasEmail}
        onChange={(e) => setSendMail(e.target.checked)}
        className="h-4 w-4 accent-brand"
      />
      {hasEmail ? `${label}（${reservation.patient_email}）` : `${label}（メールアドレスが登録されていません）`}
    </label>
  );

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <Link href="/admin/reservations" className="text-sm text-brand underline">
          ← 予約一覧
        </Link>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <h1 className="text-xl font-bold">{currentLabel}</h1>
        <StatusBadge reservation={reservation} />
        {reservation.needs_contact && <NeedsContactBadge />}
      </div>

      {reservation.needs_contact && (
        <p className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
          営業時間の変更で、この予約は営業時間外になりました。患者さんにお電話で連絡し、日時を変更するか、キャンセルしてください。
        </p>
      )}

      {doneMessage && (
        <p className={successBox} role="status">
          {doneMessage}
        </p>
      )}
      {mailFailed && (
        <p className={alertBox} role="alert">
          お知らせメールの送信に失敗しました（操作は完了しています）。メールアドレスを確認し、必要ならお電話でご連絡ください。
        </p>
      )}
      {errorBox}

      {/* ---- 日時の変更 ---- */}
      {mode === "time" && (
        <section className={`${panel} space-y-4 px-4 py-4`}>
          <h2 className="text-lg font-bold">予約日時の変更</h2>
          <SlotPicker
            value={newStartAt}
            onChange={(value) => {
              setNewStartAt(value);
              setFieldErrors({});
            }}
            initialDate={reservation.start_at.split(" ")[0]}
            currentStartAt={reservation.start_at}
            reloadKey={slotReloadKey}
          />
          {fieldErrors.start_at && (
            <p className="text-sm text-red-700" role="alert">
              {fieldErrors.start_at}
            </p>
          )}
          <dl className="grid gap-1 text-sm sm:grid-cols-[6rem_1fr]">
            <dt className="text-gray-600">変更前</dt>
            <dd className="text-gray-500 line-through">{currentLabel}</dd>
            <dt className="text-gray-600">変更後</dt>
            <dd className="font-bold">{newStartAt ? formatRange(newStartAt, slotEnd(newStartAt)) : "（枠を選んでください）"}</dd>
          </dl>
          {mailCheckbox("変更のお知らせメールを送る")}
          <div className="flex flex-wrap gap-2">
            <button type="button" className={adminPrimaryButton} onClick={changeTime} disabled={submitting || newStartAt === null}>
              {submitting ? "変更中…" : "この日時に変更する"}
            </button>
            {backButton}
          </div>
        </section>
      )}

      {/* ---- 患者情報の修正 ---- */}
      {mode === "info" && patientDraft && (
        <section className={`${panel} space-y-4 px-4 py-4`}>
          <h2 className="text-lg font-bold">患者情報・施術希望内容の修正</h2>
          <p className="text-sm text-gray-600">修正しても、患者さんにメールは送りません。</p>
          <PatientFields value={patientDraft} errors={fieldErrors} onChange={setPatientDraft} />
          <div className="flex flex-wrap gap-2">
            <button type="button" className={adminPrimaryButton} onClick={changeInfo} disabled={submitting}>
              {submitting ? "保存中…" : "保存する"}
            </button>
            {backButton}
          </div>
        </section>
      )}

      {/* ---- キャンセルの確認 ---- */}
      {mode === "cancel" && (
        <section className={`${panel} space-y-4 border-red-200 px-4 py-4`}>
          <h2 className="text-lg font-bold">この予約をキャンセルします。よろしいですか？</h2>
          <p className="font-bold">
            {currentLabel}　{reservation.patient_name} 様
          </p>
          {mailCheckbox("キャンセルのお知らせメールを送る")}
          <div className="flex flex-wrap gap-2">
            <button type="button" className={adminDangerButton} onClick={cancel} disabled={submitting}>
              {submitting ? "キャンセル中…" : "キャンセルする"}
            </button>
            <button type="button" className={adminSecondaryButton} onClick={() => goTo("view")} disabled={submitting}>
              戻る
            </button>
          </div>
        </section>
      )}

      {/* ---- 再送の確認 ---- */}
      {mode === "resend" && (
        <section className={`${panel} space-y-4 px-4 py-4`}>
          <h2 className="text-lg font-bold">予約内容のお知らせを送り直します。よろしいですか？</h2>
          <p className="text-sm">
            送り先：<span className="font-bold">{reservation.patient_email}</span>
          </p>
          <p className="text-sm text-gray-700">
            予約管理ページの URL を新しく作ってお送りします。
            {reservation.has_manage_url && (
              <span className="font-bold text-red-700">以前お送りしたメールの URL は使えなくなります。</span>
            )}
          </p>
          <div className="flex flex-wrap gap-2">
            <button type="button" className={adminPrimaryButton} onClick={resend} disabled={submitting}>
              {submitting ? "送信中…" : "送信する"}
            </button>
            <button type="button" className={adminSecondaryButton} onClick={() => goTo("view")} disabled={submitting}>
              戻る
            </button>
          </div>
        </section>
      )}

      {/* ---- 表示 ---- */}
      {mode === "view" && (
        <>
          <section className={panel}>
            <dl className="divide-y divide-gray-100 text-sm">
              <Row label="氏名">{reservation.patient_name}</Row>
              <Row label="電話番号">
                <a href={`tel:${reservation.patient_phone}`} className="text-brand underline">
                  {reservation.patient_phone}
                </a>
              </Row>
              <Row label="メールアドレス">{reservation.patient_email ?? <span className="text-gray-400">なし</span>}</Row>
              <Row label="初回／再来">{VISIT_TYPE_LABELS[reservation.visit_type]}</Row>
              <Row label="施術希望内容">
                <span className="whitespace-pre-wrap">{reservation.treatment_request ?? "－"}</span>
              </Row>
              <Row label="登録">
                {reservation.source === "web" ? "ネット予約" : "院で登録"}（{reservation.created_at}）
              </Row>
              <Row label="予約管理 URL">{reservation.has_manage_url ? "発行済み" : "未発行"}</Row>
              {reservation.status === "cancelled" && (
                <Row label="キャンセル">
                  {reservation.cancelled_at}（{reservation.cancelled_by === "admin" ? "院でキャンセル" : "患者さんがキャンセル"}）
                </Row>
              )}
            </dl>
          </section>

          {reservation.status === "reserved" && (
            <div className="flex flex-wrap gap-2">
              {canOperate && (
                <button type="button" className={adminPrimaryButton} onClick={() => startAction("time")}>
                  日時を変更する
                </button>
              )}
              <button type="button" className={adminSecondaryButton} onClick={() => startAction("info")}>
                患者情報を修正する
              </button>
              {canOperate && hasEmail && (
                <button type="button" className={adminSecondaryButton} onClick={() => startAction("resend")}>
                  予約内容のメールを再送する
                </button>
              )}
              {canOperate && (
                <button type="button" className={adminDangerButton} onClick={() => startAction("cancel")}>
                  予約をキャンセルする
                </button>
              )}
            </div>
          )}
          {reservation.status === "reserved" && !canOperate && (
            <p className="text-sm text-gray-600">予約時刻を過ぎたため、日時の変更・キャンセル・メールの再送はできません。</p>
          )}

          <MailLogSection reservation={reservation} />
        </>
      )}
    </div>
  );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid gap-1 px-4 py-3 sm:grid-cols-[9rem_1fr]">
      <dt className="text-gray-600">{label}</dt>
      <dd className="break-all">{children}</dd>
    </div>
  );
}

/** この予約に送ったメールの記録（新しい順） */
function MailLogSection({ reservation }: { reservation: AdminReservation }) {
  const logs = reservation.mail_logs ?? [];

  return (
    <section className="space-y-2">
      <h2 className="text-lg font-bold">メールの送信記録</h2>
      {logs.length === 0 ? (
        <p className="text-sm text-gray-600">この予約に送ったメールはありません。</p>
      ) : (
        <ul className={`${panel} divide-y divide-gray-100 text-sm`}>
          {logs.map((log) => (
            <li key={log.id} className="space-y-1 px-4 py-3">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-gray-600">{log.created_at}</span>
                <span className="font-bold">{MAIL_TYPE_LABELS[log.type]}</span>
                <MailResultBadge log={log} />
              </div>
              <p className="break-all text-gray-600">宛先：{log.to_email}</p>
              {log.error_message && <p className="break-all text-red-700">{log.error_message}</p>}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
