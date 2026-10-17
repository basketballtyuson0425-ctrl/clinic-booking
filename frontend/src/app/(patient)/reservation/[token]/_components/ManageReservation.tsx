"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import DateStep from "@/components/DateStep";
import TimeStep from "@/components/TimeStep";
import { alertBox, dangerButton, primaryButton, secondaryButton, successBox } from "@/components/ui";
import {
  ApiError,
  cancelManagedReservation,
  fetchManagedReservation,
  updateManagedReservation,
  type ManagedReservation,
  type ManageResponse,
} from "@/lib/api";
import { clinic } from "@/lib/clinic";
import { formatDateTime } from "@/lib/date";
import InvalidLink from "./InvalidLink";
import ReservationSummary from "./ReservationSummary";

// 画面の状態：表示中／日付を選ぶ／時間を選ぶ／日時の変更を確認／施術希望内容を編集／キャンセルを確認
type Mode = "view" | "date" | "time" | "confirmTime" | "treatment" | "confirmCancel";

type Props = {
  token: string;
};

/**
 * 予約管理ページの本体。予約内容を表示し、予約日時の変更・施術希望内容の変更・キャンセルを行う。
 */
export default function ManageReservation({ token }: Props) {
  const [reservation, setReservation] = useState<ManagedReservation | null>(null);
  const [notFound, setNotFound] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [mode, setMode] = useState<Mode>("view");
  const [newDate, setNewDate] = useState<string | null>(null);
  const [newTime, setNewTime] = useState<string | null>(null);
  const [timeNotice, setTimeNotice] = useState<string | null>(null);
  const [treatmentDraft, setTreatmentDraft] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [doneMessage, setDoneMessage] = useState<string | null>(null);

  // 表示されたときに、予約内容を読み込む
  useEffect(() => {
    let ignore = false;
    fetchManagedReservation(token)
      .then((response) => {
        if (!ignore) setReservation(response.reservation);
      })
      .catch((e: unknown) => {
        if (ignore) return;
        if (e instanceof ApiError && e.status === 404) {
          setNotFound(true);
        } else {
          setLoadError(e instanceof ApiError ? e.message : "読み込みに失敗しました。");
        }
      });
    return () => {
      ignore = true;
    };
  }, [token]);

  function goTo(next: Mode) {
    setMode(next);
    setActionError(null);
    window.scrollTo({ top: 0 });
  }

  /** 変更・キャンセルが成功したら、最新の予約内容と完了メッセージを表示する */
  function showResult(response: ManageResponse, doneText: string) {
    setReservation(response.reservation);
    let mailText = "";
    if (response.mail_sent) {
      mailText = "確認メールを送信しました。";
    } else if (response.reservation.has_email) {
      mailText = "確認メールの送信に失敗しましたが、お手続きは完了しています。";
    }
    setDoneMessage(`${doneText}${mailText}`);
    goTo("view");
  }

  /** 変更・キャンセルが失敗したときに、理由に合わせて画面を戻す */
  function handleError(e: unknown) {
    if (!(e instanceof ApiError)) {
      setActionError("エラーが発生しました。時間をおいてもう一度お試しください。");
      return;
    }

    if (e.status === 404) {
      setNotFound(true);
      return;
    }

    // 期限を過ぎたなどで、変更・キャンセルできなくなった → 最新の状態を読み込み直して表示する
    if (e.status === 403) {
      setDoneMessage(null);
      setMode("view");
      setActionError(e.message);
      fetchManagedReservation(token)
        .then((response) => setReservation(response.reservation))
        .catch(() => {});
      return;
    }

    // 選んだ時間が直前に埋まった、または予約できない時間 → 時間の選択に戻る（最新の ○／× を読み込み直す）
    const startAtError = e.fieldErrors.start_at?.[0];
    if (mode === "confirmTime" && ((e.status === 409 && e.reason === "slot_taken") || startAtError)) {
      setNewTime(null);
      setTimeNotice(startAtError ?? e.message);
      goTo("time");
      return;
    }

    const firstFieldError = Object.values(e.fieldErrors)[0]?.[0];
    setActionError(firstFieldError ?? e.message);
  }

  /** 送信中はボタンを押せなくし、終わったら戻す */
  async function submit(action: () => Promise<void>) {
    if (submitting) return;
    setSubmitting(true);
    setActionError(null);
    try {
      await action();
    } catch (e: unknown) {
      handleError(e);
    } finally {
      setSubmitting(false);
    }
  }

  function changeTime() {
    if (newDate === null || newTime === null) return;
    return submit(async () => {
      const response = await updateManagedReservation(token, { start_at: `${newDate} ${newTime}` });
      showResult(response, "予約日時を変更しました。");
    });
  }

  function changeTreatment() {
    const value = treatmentDraft.trim();
    if ([...value].length > 500) {
      setActionError("施術希望内容は500文字以内で入力してください。");
      return;
    }
    return submit(async () => {
      const response = await updateManagedReservation(token, { treatment_request: value });
      showResult(response, response.changed ? "施術希望内容を変更しました。" : "変更はありませんでした。");
    });
  }

  function cancel() {
    return submit(async () => {
      const response = await cancelManagedReservation(token);
      showResult(response, "ご予約をキャンセルしました。");
    });
  }

  if (notFound) {
    return <InvalidLink />;
  }

  if (loadError) {
    return (
      <p className={alertBox} role="alert">
        {loadError}
      </p>
    );
  }

  if (reservation === null) {
    return <p className="py-10 text-center text-gray-500">読み込み中…</p>;
  }

  const currentLabel = formatDateTime(reservation.start_at);
  const errorBox = actionError && (
    <p className={alertBox} role="alert">
      {actionError}
    </p>
  );
  const stopButton = (
    <button type="button" className={secondaryButton} onClick={() => goTo("view")} disabled={submitting}>
      変更をやめる
    </button>
  );

  // ---- 日付・時間を選ぶ ----
  if (mode === "date" || (mode === "time" && newDate !== null)) {
    return (
      <div className="space-y-4">
        <p className={successBox}>現在のご予約：{currentLabel}</p>
        {mode === "date" ? (
          <DateStep
            onSelect={(date) => {
              setNewDate(date);
              setNewTime(null);
              setTimeNotice(null);
              goTo("time");
            }}
          />
        ) : (
          <TimeStep
            date={newDate!}
            notice={timeNotice}
            onSelect={(time) => {
              setNewTime(time);
              goTo("confirmTime");
            }}
            onBack={() => goTo("date")}
          />
        )}
        {stopButton}
      </div>
    );
  }

  // ---- 日時の変更を確認する ----
  if (mode === "confirmTime" && newDate !== null && newTime !== null) {
    return (
      <div className="space-y-5">
        <h2 className="text-lg font-bold">予約日時を次のとおり変更します</h2>
        <dl className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
          <div className="px-4 py-3">
            <dt className="text-sm text-gray-600">変更前</dt>
            <dd className="mt-1 text-base text-gray-500 line-through">{currentLabel}</dd>
          </div>
          <div className="px-4 py-3">
            <dt className="text-sm text-gray-600">変更後</dt>
            <dd className="mt-1 text-lg font-bold">{formatDateTime(`${newDate} ${newTime}`)}</dd>
          </div>
        </dl>
        {errorBox}
        <div className="space-y-3">
          <button type="button" className={primaryButton} onClick={changeTime} disabled={submitting}>
            {submitting ? "送信中…" : "この日時に変更する"}
          </button>
          <button type="button" className={secondaryButton} onClick={() => goTo("time")} disabled={submitting}>
            時間を選び直す
          </button>
          {stopButton}
        </div>
      </div>
    );
  }

  // ---- 施術希望内容を変更する ----
  if (mode === "treatment") {
    return (
      <div className="space-y-5">
        <div>
          <label htmlFor="treatmentRequest" className="mb-2 block text-lg font-bold">
            施術希望内容
          </label>
          <textarea
            id="treatmentRequest"
            rows={4}
            placeholder="例：腰痛、肩こり など"
            value={treatmentDraft}
            onChange={(e) => setTreatmentDraft(e.target.value)}
            className="block w-full rounded-md border border-gray-300 bg-white px-3 py-3 text-base focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-light"
          />
        </div>
        {errorBox}
        <div className="space-y-3">
          <button type="button" className={primaryButton} onClick={changeTreatment} disabled={submitting}>
            {submitting ? "送信中…" : "保存する"}
          </button>
          {stopButton}
        </div>
      </div>
    );
  }

  // ---- キャンセルを確認する ----
  if (mode === "confirmCancel") {
    return (
      <div className="space-y-5">
        <h2 className="text-lg font-bold">次のご予約をキャンセルします。よろしいですか？</h2>
        <p className="rounded-lg border border-gray-200 bg-white px-4 py-3 text-lg font-bold">{currentLabel}</p>
        {errorBox}
        <div className="space-y-3">
          <button type="button" className={dangerButton} onClick={cancel} disabled={submitting}>
            {submitting ? "送信中…" : "キャンセルする"}
          </button>
          <button type="button" className={secondaryButton} onClick={() => goTo("view")} disabled={submitting}>
            戻る
          </button>
        </div>
      </div>
    );
  }

  // ---- 表示 ----
  return (
    <div className="space-y-5">
      {doneMessage && (
        <p className={successBox} role="status">
          {doneMessage}
        </p>
      )}
      {errorBox}

      <ReservationSummary reservation={reservation} />

      {reservation.can_modify ? (
        <div className="space-y-3">
          <p className="text-sm text-gray-700">
            予約日時・施術希望内容の変更とキャンセルは、{formatDateTime(reservation.modifiable_until)}
            まで、このページから行えます。
          </p>
          <button
            type="button"
            className={primaryButton}
            onClick={() => {
              setDoneMessage(null);
              setNewDate(null);
              setNewTime(null);
              setTimeNotice(null);
              goTo("date");
            }}
          >
            予約日時を変更する
          </button>
          <button
            type="button"
            className={secondaryButton}
            onClick={() => {
              setDoneMessage(null);
              setTreatmentDraft(reservation.treatment_request ?? "");
              goTo("treatment");
            }}
          >
            施術希望内容を変更する
          </button>
          <button
            type="button"
            className={dangerButton}
            onClick={() => {
              setDoneMessage(null);
              goTo("confirmCancel");
            }}
          >
            予約をキャンセルする
          </button>
        </div>
      ) : (
        <CannotModifyNotice reason={reservation.cannot_modify_reason} />
      )}
    </div>
  );
}

/** 変更・キャンセルできない理由に合わせた案内 */
function CannotModifyNotice({ reason }: { reason: ManagedReservation["cannot_modify_reason"] }) {
  if (reason === "cancelled") {
    return (
      <div className="space-y-3">
        <p className="text-sm text-gray-700">このご予約はキャンセル済みです。</p>
        <Link href="/reserve" className={primaryButton}>
          新しく予約する
        </Link>
      </div>
    );
  }

  const message =
    reason === "no_email"
      ? "メールアドレスのご登録がないため、このページではご予約内容の確認のみできます。"
      : "予約時刻の1時間前を過ぎたため、このページからは変更・キャンセルできません。";

  return (
    <p className="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
      {message}
      <br />
      ご予約の変更・キャンセルは、お電話でお願いします。
      <br />
      電話：
      <a href={`tel:${clinic.phone}`} className="font-bold text-brand underline">
        {clinic.phone}
      </a>
    </p>
  );
}
