"use client";

import { useState } from "react";
import { ApiError, createReservation, type ReservationCreated } from "@/lib/api";
import ConfirmStep from "./ConfirmStep";
import DateStep from "./DateStep";
import DoneStep from "./DoneStep";
import { emptyForm, SERVER_FIELD_TO_FORM, toReservationInput, type FormErrors, type ReservationForm } from "./form";
import InfoStep from "./InfoStep";
import StepIndicator from "./StepIndicator";
import TimeStep from "./TimeStep";

type Step = "date" | "time" | "info" | "confirm" | "done";
const STEP_ORDER: Step[] = ["date", "time", "info", "confirm", "done"];

/**
 * 予約の流れ全体の進行役。
 * 「今どの段階か」と、選んだ日時・入力内容を覚えておき、段階に応じた画面を表示する。
 * URL は /reserve のまま、1ページの中で画面を切り替える。
 */
export default function ReservationFlow() {
  const [step, setStep] = useState<Step>("date");
  const [date, setDate] = useState<string | null>(null);
  const [time, setTime] = useState<string | null>(null);
  const [form, setForm] = useState<ReservationForm>(emptyForm);
  const [serverErrors, setServerErrors] = useState<FormErrors>({});
  const [timeNotice, setTimeNotice] = useState<string | null>(null);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState<ReservationCreated | null>(null);

  function goTo(next: Step) {
    setStep(next);
    window.scrollTo({ top: 0 });
  }

  function selectDate(selected: string) {
    setDate(selected);
    setTime(null);
    setTimeNotice(null);
    goTo("time");
  }

  function selectTime(selected: string) {
    setTime(selected);
    setServerErrors({});
    goTo("info");
  }

  async function submit() {
    if (date === null || time === null || submitting) return;

    setSubmitting(true);
    setConfirmError(null);
    try {
      const created = await createReservation(toReservationInput(form, `${date} ${time}`));
      setResult(created);
      goTo("done");
    } catch (e: unknown) {
      handleSubmitError(e);
    } finally {
      setSubmitting(false);
    }
  }

  /** 予約できなかったとき、理由に合わせて戻る画面を決める */
  function handleSubmitError(e: unknown) {
    if (!(e instanceof ApiError)) {
      setConfirmError("エラーが発生しました。時間をおいてもう一度お試しください。");
      return;
    }

    // 選んだ時間が直前に埋まった、または予約できない時間になった → 時間の選択に戻る（最新の ○／× を読み込み直す）
    const startAtError = e.fieldErrors.start_at?.[0];
    if ((e.status === 409 && e.reason === "slot_taken") || startAtError) {
      setTime(null);
      setTimeNotice(startAtError ?? e.message);
      goTo("time");
      return;
    }

    // 入力内容の誤り → 入力画面に戻り、欄ごとにエラーを表示する
    if (e.status === 422) {
      const errors: FormErrors = {};
      for (const [field, messages] of Object.entries(e.fieldErrors)) {
        const formField = SERVER_FIELD_TO_FORM[field];
        if (formField) errors[formField] = messages[0];
      }
      setServerErrors(errors);
      goTo("info");
      return;
    }

    // 同じ日にすでに予約がある・アクセスが多すぎる・通信失敗など → 確認画面にメッセージを出す
    setConfirmError(e.message);
  }

  const startAt = date !== null && time !== null ? `${date} ${time}` : null;

  return (
    <div>
      <StepIndicator current={STEP_ORDER.indexOf(step)} />

      {step === "date" && <DateStep onSelect={selectDate} />}

      {step === "time" && date !== null && (
        <TimeStep date={date} notice={timeNotice} onSelect={selectTime} onBack={() => goTo("date")} />
      )}

      {step === "info" && startAt !== null && (
        <InfoStep
          startAt={startAt}
          form={form}
          initialErrors={serverErrors}
          onChange={setForm}
          onNext={() => {
            setConfirmError(null);
            goTo("confirm");
          }}
          onBack={() => goTo("time")}
        />
      )}

      {step === "confirm" && startAt !== null && (
        <ConfirmStep
          startAt={startAt}
          form={form}
          submitting={submitting}
          error={confirmError}
          onSubmit={submit}
          onBack={() => goTo("info")}
        />
      )}

      {step === "done" && result !== null && <DoneStep result={result} />}
    </div>
  );
}
