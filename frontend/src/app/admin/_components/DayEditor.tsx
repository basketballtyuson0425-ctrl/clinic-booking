"use client";

import Link from "next/link";
import { useState } from "react";
import { alertBox, successBox } from "@/components/ui";
import {
  checkDayImpact,
  resetBusinessDay,
  saveBusinessDay,
  type AffectedReservation,
  type CalendarDay,
  type HoursImpact,
  type HoursInput,
  type MailResult,
} from "@/lib/adminApi";
import { ApiError } from "@/lib/api";
import { formatDate, todayString } from "@/lib/date";
import { formatHours, formatPeriod, hoursFromDay } from "./hours";
import HoursFields from "./HoursFields";
import ImpactConfirm from "./ImpactConfirm";
import { toFieldErrors } from "./PatientFields";
import { adminDangerButton, adminPrimaryButton, adminSecondaryButton, panel } from "./styles";
import { useAdminError } from "./useAdminError";

// 画面の状態：見るだけ／編集中／保存前の確認
type Mode = "view" | "edit" | "confirm";

type Props = {
  day: CalendarDay;
  onSaved: () => void; // 保存したら、カレンダーを読み込み直す
};

const SOURCE_LABELS: Record<CalendarDay["source"], string> = {
  override: "この日だけの設定",
  holiday: "祝日のため休診",
  weekly: "基本の営業時間（曜日ごと）",
};

/**
 * カレンダーで選んだ日の営業時間。普段は見るだけで、「編集」を押したときだけ変更できる（要件20〜24）。
 * 予約がある日は、保存の前に影響を確認する（要件25）
 */
export default function DayEditor({ day, onSaved }: Props) {
  const toErrorMessage = useAdminError();
  const [mode, setMode] = useState<Mode>("view");
  const [draft, setDraft] = useState<HoursInput>(() => hoursFromDay(day));
  const [action, setAction] = useState<"save" | "reset">("save"); // 確認のあとに行う操作
  const [impact, setImpact] = useState<HoursImpact | null>(null);
  const [sendMail, setSendMail] = useState(true);
  const [errors, setErrors] = useState<Record<string, string | undefined>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [done, setDone] = useState<{ affected: AffectedReservation[]; mail: MailResult } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const editable = day.date >= todayString(); // 過去の日は変更できない

  function showError(e: unknown) {
    if (e instanceof ApiError && e.status === 422 && Object.keys(e.fieldErrors).length > 0) {
      setErrors(toFieldErrors(e.fieldErrors));
      setMessage(e.fieldErrors.date?.[0] ?? null);
      setMode("edit");
      return;
    }
    setMessage(toErrorMessage(e));
  }

  /**
   * 送信中はボタンを押せなくし、エラーは理由に合わせて表示する
   *
   * @param next 保存するか、通常設定に戻すか（確認をやり直すときに使う）
   */
  async function run(next: "save" | "reset", task: () => Promise<void>) {
    if (submitting) return;
    setSubmitting(true);
    setMessage(null);
    setErrors({});
    try {
      await task();
    } catch (e: unknown) {
      // 確認のあいだに予約が入ったなど、サーバーが「確認が必要」と断った → 影響を調べ直して確認を出す
      if (e instanceof ApiError && e.status === 409 && e.reason === "confirmation_required") {
        await prepare(next).catch(showError);
      } else {
        showError(e);
      }
    } finally {
      setSubmitting(false);
    }
  }

  /** 影響を調べる。予約があれば確認を出し、なければそのまま保存する */
  async function prepare(next: "save" | "reset") {
    const result = await checkDayImpact(day.date, next === "reset" ? { reset: true } : draft);
    setAction(next);
    if (result.reservation_count === 0) {
      await commit(next, false);
      return;
    }
    setImpact(result);
    setSendMail(true);
    setMode("confirm");
  }

  async function commit(next: "save" | "reset", confirmed: boolean) {
    const options = { confirmed, send_mail: confirmed && sendMail };
    const result =
      next === "reset" ? await resetBusinessDay(day.date, options) : await saveBusinessDay(day.date, { ...draft, ...options });
    setDone({ affected: result.affected, mail: result.mail });
    setMode("view");
    onSaved();
  }

  return (
    <section className={`${panel} space-y-4 px-4 py-4`} aria-label={`${formatDate(day.date)}の営業時間`}>
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-lg font-bold">
          {formatDate(day.date)}
          {day.holiday_name && <span className="ml-2 text-sm font-normal text-red-700">{day.holiday_name}</span>}
        </h2>
        <span className="text-sm text-gray-600">{SOURCE_LABELS[day.source]}</span>
      </div>

      {done && <SavedMessage done={done} />}
      {message && (
        <p className={alertBox} role="alert">
          {message}
        </p>
      )}

      {/* ---- 見るだけ ---- */}
      {mode === "view" && (
        <>
          {day.is_closed ? (
            <p className="text-base font-bold">{formatDate(day.date)} 休診</p>
          ) : (
            <dl className="grid grid-cols-[4rem_1fr] gap-1 text-base">
              <dt className="text-gray-600">前半</dt>
              <dd className="font-bold">{formatPeriod(day.am)}</dd>
              <dt className="text-gray-600">後半</dt>
              <dd className="font-bold">{formatPeriod(day.pm)}</dd>
            </dl>
          )}
          <p className="text-sm text-gray-700">
            予約 {day.reservation_count} 件
            {day.reservation_count > 0 && (
              <Link href={`/admin/reservations?date=${day.date}`} className="ml-2 text-brand underline">
                この日の予約を見る
              </Link>
            )}
            {day.needs_contact_count > 0 && (
              <span className="ml-2 font-bold text-amber-700">（要連絡 {day.needs_contact_count} 件）</span>
            )}
          </p>
          {editable ? (
            <button
              type="button"
              className={adminPrimaryButton}
              onClick={() => {
                setDraft(hoursFromDay(day));
                setDone(null);
                setMessage(null);
                setErrors({});
                setMode("edit");
              }}
            >
              編集
            </button>
          ) : (
            <p className="text-sm text-gray-500">過去の日付の営業時間は変更できません。</p>
          )}
        </>
      )}

      {/* ---- 編集中 ---- */}
      {mode === "edit" && (
        <div className="space-y-4">
          <HoursFields id="day" value={draft} errors={errors} closedLabel="この日を休診にする" onChange={setDraft} />
          <div className="flex flex-wrap gap-2">
            <button type="button" className={adminPrimaryButton} onClick={() => run("save", () => prepare("save"))} disabled={submitting}>
              {submitting ? "確認中…" : "保存"}
            </button>
            <button type="button" className={adminSecondaryButton} onClick={() => setMode("view")} disabled={submitting}>
              キャンセル
            </button>
            {day.has_override && (
              <button type="button" className={adminDangerButton} onClick={() => run("reset", () => prepare("reset"))} disabled={submitting}>
                通常設定に戻す
              </button>
            )}
          </div>
          {day.has_override && (
            <p className="text-xs text-gray-500">「通常設定に戻す」：この日だけの設定を消して、基本の営業時間（祝日なら休診）に戻します。</p>
          )}
        </div>
      )}

      {/* ---- 保存前の確認 ---- */}
      {mode === "confirm" && impact && (
        <div className="space-y-3">
          <p className="text-sm">
            変更後：
            <span className="font-bold">
              {action === "reset" && impact.schedule
                ? `通常の設定（${impact.schedule.is_closed ? "休診" : `前半 ${formatPeriod(impact.schedule.am)}／後半 ${formatPeriod(impact.schedule.pm)}`}）`
                : formatHours(draft)}
            </span>
          </p>
          <ImpactConfirm
            impact={impact}
            message={`この日には既に${impact.reservation_count}件の予約があります。営業時間を変更すると予約時間に影響する可能性があります。変更しますか？`}
            sendMail={sendMail}
            submitting={submitting}
            onSendMailChange={setSendMail}
            onConfirm={() => run(action, () => commit(action, true))}
            onBack={() => setMode("edit")}
          />
        </div>
      )}
    </section>
  );
}

/** 保存したあとのお知らせ（要連絡の件数・メールの結果） */
function SavedMessage({ done }: { done: { affected: AffectedReservation[]; mail: MailResult } }) {
  return (
    <div className="space-y-2">
      <p className={successBox} role="status">
        営業時間を保存しました。
        {done.mail.sent > 0 && `お知らせメールを ${done.mail.sent} 件送信しました。`}
      </p>
      {done.mail.failed > 0 && (
        <p className={alertBox} role="alert">
          お知らせメールのうち {done.mail.failed} 件は送信に失敗しました。メール送信記録を確認してください。
        </p>
      )}
      {done.affected.length > 0 && (
        <p className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          営業時間外になった予約が {done.affected.length} 件あります（要連絡）。
          <Link href="/admin/reservations?needs_contact=1" className="ml-1 font-bold underline">
            要連絡の予約を見る
          </Link>
        </p>
      )}
    </div>
  );
}
