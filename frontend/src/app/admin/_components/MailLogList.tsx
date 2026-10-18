"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { alertBox } from "@/components/ui";
import { fetchMailLogs, type MailLogEntry, type Paginated } from "@/lib/adminApi";
import { formatDateTime } from "@/lib/date";
import { MAIL_TYPE_LABELS, MailResultBadge } from "./labels";
import Pagination from "./Pagination";
import { panel } from "./styles";
import { useAdminError } from "./useAdminError";

type Props = {
  failedOnly: boolean;
  page: number;
};

/** URL：/admin/mail-logs?failed_only=1&page=2 */
function hrefFor(failedOnly: boolean, page: number): string {
  const params = new URLSearchParams();
  if (failedOnly) params.set("failed_only", "1");
  if (page > 1) params.set("page", String(page));
  const query = params.toString();
  return query === "" ? "/admin/mail-logs" : `/admin/mail-logs?${query}`;
}

/** メールの送信記録（/admin/mail-logs）。新しい順。送信に失敗したものだけに絞り込める */
export default function MailLogList({ failedOnly, page }: Props) {
  const router = useRouter();
  const toErrorMessage = useAdminError();
  const [result, setResult] = useState<Paginated<MailLogEntry> | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let ignore = false;
    fetchMailLogs(failedOnly, page)
      .then((response) => {
        if (!ignore) setResult(response);
      })
      .catch((e: unknown) => {
        if (!ignore) setError(toErrorMessage(e));
      });
    return () => {
      ignore = true;
    };
  }, [failedOnly, page, toErrorMessage]);

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">メール送信記録</h1>

      <label className="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          checked={failedOnly}
          onChange={(e) => router.push(hrefFor(e.target.checked, 1))}
          className="h-4 w-4 accent-brand"
        />
        送信に失敗したメールだけを表示
      </label>

      {error && (
        <p className={alertBox} role="alert">
          {error}
        </p>
      )}
      {!error && result === null && <p className="py-6 text-center text-gray-500">読み込み中…</p>}

      {result && result.data.length === 0 && (
        <p className={`${panel} px-4 py-8 text-center text-sm text-gray-600`}>該当するメールはありません。</p>
      )}

      {result && result.data.length > 0 && (
        <>
          <p className="text-sm text-gray-600">
            {result.meta.total} 件（{result.meta.from}〜{result.meta.to} 件目を表示）
          </p>
          <ul className={`${panel} divide-y divide-gray-100 text-sm`}>
            {result.data.map((log) => (
              <li
                key={log.id}
                className={`grid gap-1 px-4 py-3 md:grid-cols-[9rem_12rem_1fr_1fr] md:items-center md:gap-3 ${
                  log.status === "failed" ? "bg-red-50" : ""
                }`}
              >
                <span className="text-gray-600">{log.created_at}</span>
                <span className="flex items-center gap-2">
                  <MailResultBadge log={log} />
                  <span className="font-bold whitespace-nowrap">{MAIL_TYPE_LABELS[log.type]}</span>
                </span>
                <span className="break-all">
                  {log.to_email}
                  {log.error_message && <span className="block text-red-700">{log.error_message}</span>}
                </span>
                {log.reservation && (
                  <Link href={`/admin/reservations/${log.reservation.id}`} className="text-brand underline">
                    {formatDateTime(log.reservation.start_at)}　{log.reservation.patient_name} 様
                  </Link>
                )}
              </li>
            ))}
          </ul>
          <Pagination
            page={result.meta.current_page}
            lastPage={result.meta.last_page}
            hrefFor={(next) => hrefFor(failedOnly, next)}
          />
        </>
      )}
    </div>
  );
}
