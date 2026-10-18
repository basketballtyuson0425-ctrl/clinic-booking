import type { Metadata } from "next";
import MailLogList from "../../_components/MailLogList";

export const metadata: Metadata = {
  title: "メール送信記録",
};

/** メールの送信記録（/admin/mail-logs?failed_only=1&page=2） */
export default async function MailLogsPage({ searchParams }: PageProps<"/admin/mail-logs">) {
  const query = await searchParams;
  const failedOnly = query.failed_only === "1";
  const page = Number(query.page);

  return <MailLogList failedOnly={failedOnly} page={Number.isInteger(page) && page > 1 ? page : 1} />;
}
