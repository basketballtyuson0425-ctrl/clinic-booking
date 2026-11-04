"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { alertBox, successBox } from "@/components/ui";
import {
  checkWeeklyImpact,
  fetchWeeklySchedules,
  saveWeeklySchedules,
  type AffectedReservation,
  type HoursImpact,
  type MailResult,
  type WeeklySchedule,
} from "@/lib/adminApi";
import { ApiError } from "@/lib/api";
import { WEEKDAY_LABELS } from "@/lib/date";
import { formatHours } from "./hours";
import HoursFields from "./HoursFields";
import ImpactConfirm from "./ImpactConfirm";
import { toFieldErrors } from "./PatientFields";
import { adminPrimaryButton, adminSecondaryButton, noticeLine } from "./styles";
import { useAdminError } from "./useAdminError";

type Mode = "view" | "edit" | "confirm";

type Props = {
  onSaved: () => void; // 保存したら、カレンダーを読み込み直す
};

/** [1, 3] → "第1・第3週は休診" */
function closedWeeksLabel(weeks: number[]): string {
  return weeks.length === 0 ? "" : `（${weeks.map((week) => `第${week}`).join("・")}週は休診）`;
}

/**
 * 曜日ごとの基本の営業時間。日付ごとの設定がない日（祝日を除く）は、この営業時間になる。
 * これから先の予約が営業時間から外れる場合は、保存の前に確認する
 */
export default function WeeklyEditor({ onSaved }: Props) {
  const toErrorMessage = useAdminError();
  const [schedules, setSchedules] = useState<WeeklySchedule[] | null>(null);
  const [draft, setDraft] = useState<WeeklySchedule[]>([]);
  const [mode, setMode] = useState<Mode>("view");
  const [impact, setImpact] = useState<HoursImpact | null>(null);
  const [sendMail, setSendMail] = useState(true);
  const [errors, setErrors] = useState<Record<string, string | undefined>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [done, setDone] = useState<{ affected: AffectedReservation[]; mail: MailResult } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let ignore = false;
    fetchWeeklySchedules()
      .then((result) => {
        if (!ignore) setSchedules(result);
      })
      .catch((e: unknown) => {
        if (!ignore) setMessage(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [toErrorMessage]);

  function showError(e: unknown) {
    if (e instanceof ApiError && e.status === 422 && Object.keys(e.fieldErrors).length > 0) {
      setErrors(toFieldErrors(e.fieldErrors));
      setMessage("入力内容を確認してください。");
      setMode("edit");
      return;
    }
    setMessage(toErrorMessage(e));
  }

  async function run(task: () => Promise<void>) {
    if (submitting) return;
    setSubmitting(true);
    setMessage(null);
    setErrors({});
    try {
      await task();
    } catch (e: unknown) {
      if (e instanceof ApiError && e.status === 409 && e.reason === "confirmation_required") {
        await prepare().catch(showError);
      } else {
        showError(e);
      }
    } finally {
      setSubmitting(false);
    }
  }

  /** 影響を調べる。外れる予約があれば確認を出し、なければそのまま保存する */
  async function prepare() {
    const result = await checkWeeklyImpact(draft);
    if (result.reservation_count === 0) {
      await commit(false);
      return;
    }
    setImpact(result);
    setSendMail(true);
    setMode("confirm");
  }

  async function commit(confirmed: boolean) {
    const result = await saveWeeklySchedules(draft, { confirmed, send_mail: confirmed && sendMail });
    setSchedules(result.schedules);
    setDone({ affected: result.affected, mail: result.mail });
    setMode("view");
    onSaved();
  }

  function updateDay(dayOfWeek: number, next: WeeklySchedule) {
    setDraft((current) => current.map((schedule) => (schedule.day_of_week === dayOfWeek ? next : schedule)));
  }

  /** "schedules.3.am_start" のようなエラーを、その曜日の分だけ取り出す（キーは "am_start" に直す） */
  function errorsOf(index: number): Record<string, string | undefined> {
    const prefix = `schedules.${index}.`;
    return Object.fromEntries(
      Object.entries(errors)
        .filter(([key]) => key.startsWith(prefix))
        .map(([key, value]) => [key.slice(prefix.length), value]),
    );
  }

  return (
    <section className="space-y-4 border-t border-gray-300 pt-5" aria-label="基本の営業時間">
      <div>
        <h2 className="text-lg font-bold">基本の営業時間（曜日ごと）</h2>
        <p className="text-sm text-gray-600">日付ごとの設定がない日は、この営業時間になります（祝日は休診）。</p>
      </div>

      {done && (
        <div className="space-y-2">
          <p className={successBox} role="status">
            基本の営業時間を保存しました。{done.mail.sent > 0 && `お知らせメールを ${done.mail.sent} 件送信しました。`}
          </p>
          {done.mail.failed > 0 && (
            <p className={alertBox} role="alert">
              お知らせメールのうち {done.mail.failed} 件は送信に失敗しました。メール送信記録を確認してください。
            </p>
          )}
          {done.affected.length > 0 && (
            <p className={noticeLine}>
              営業時間外になった予約が {done.affected.length} 件あります（要連絡）。
              <Link href="/admin/reservations?needs_contact=1" className="ml-1 font-bold underline">
                要連絡の予約を見る
              </Link>
            </p>
          )}
        </div>
      )}
      {message && (
        <p className={alertBox} role="alert">
          {message}
        </p>
      )}

      {schedules === null && !message && <p className="text-sm text-gray-500">読み込み中…</p>}

      {/* ---- 見るだけ ---- */}
      {mode === "view" && schedules && (
        <>
          <dl className="divide-y divide-gray-100 border-y border-gray-200 text-sm">
            {schedules.map((schedule) => (
              <div key={schedule.day_of_week} className="grid grid-cols-[3rem_1fr] gap-2 py-2">
                <dt className="font-bold">{WEEKDAY_LABELS[schedule.day_of_week]}曜</dt>
                <dd>
                  {schedule.is_closed ? "毎週休診" : formatHours(schedule)}
                  {!schedule.is_closed && <span className="text-gray-600">{closedWeeksLabel(schedule.closed_weeks)}</span>}
                </dd>
              </div>
            ))}
          </dl>
          <button
            type="button"
            className={adminPrimaryButton}
            onClick={() => {
              setDraft(schedules);
              setDone(null);
              setMessage(null);
              setErrors({});
              setMode("edit");
            }}
          >
            編集
          </button>
        </>
      )}

      {/* ---- 編集中 ---- */}
      {mode === "edit" && (
        <div className="space-y-4">
          {/* 曜日ごとに箱で囲まず、下の罫線で区切る */}
          <div className="grid gap-x-10 lg:grid-cols-2">
            {draft.map((schedule, index) => (
              <fieldset key={schedule.day_of_week} className="space-y-3 border-b border-gray-200 pb-4">
                <legend className="pt-4 pb-1 font-bold">{WEEKDAY_LABELS[schedule.day_of_week]}曜</legend>
                <HoursFields
                  id={`weekly-${schedule.day_of_week}`}
                  value={schedule}
                  errors={errorsOf(index)}
                  closedLabel="毎週休診"
                  onChange={(hours) => updateDay(schedule.day_of_week, { ...schedule, ...hours })}
                />
                {!schedule.is_closed && (
                  <div className="flex flex-wrap items-center gap-3 text-sm">
                    <span className="text-gray-600">休診にする週：</span>
                    {[1, 2, 3, 4, 5].map((week) => (
                      <label key={week} className="flex items-center gap-1">
                        <input
                          type="checkbox"
                          checked={schedule.closed_weeks.includes(week)}
                          onChange={(e) =>
                            updateDay(schedule.day_of_week, {
                              ...schedule,
                              closed_weeks: e.target.checked
                                ? [...schedule.closed_weeks, week].sort((a, b) => a - b)
                                : schedule.closed_weeks.filter((value) => value !== week),
                            })
                          }
                          className="h-4 w-4 accent-brand"
                        />
                        第{week}
                      </label>
                    ))}
                  </div>
                )}
              </fieldset>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <button type="button" className={adminPrimaryButton} onClick={() => run(prepare)} disabled={submitting}>
              {submitting ? "確認中…" : "保存"}
            </button>
            <button type="button" className={adminSecondaryButton} onClick={() => setMode("view")} disabled={submitting}>
              キャンセル
            </button>
          </div>
        </div>
      )}

      {/* ---- 保存前の確認 ---- */}
      {mode === "confirm" && impact && (
        <ImpactConfirm
          impact={impact}
          message={`これから先の予約のうち、${impact.reservation_count}件が新しい営業時間から外れます。変更しますか？`}
          sendMail={sendMail}
          submitting={submitting}
          onSendMailChange={setSendMail}
          onConfirm={() => run(() => commit(true))}
          onBack={() => setMode("edit")}
        />
      )}
    </section>
  );
}
