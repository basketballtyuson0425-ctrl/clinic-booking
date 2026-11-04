import Link from "next/link";
import { adminQuietButton } from "./styles";

type Props = {
  page: number;
  lastPage: number;
  hrefFor: (page: number) => string; // そのページを開く URL
};

/** 「前へ／次へ」のページ送り（ページは URL の ?page= で表すので、戻るボタンでも元のページに戻れる） */
export default function Pagination({ page, lastPage, hrefFor }: Props) {
  if (lastPage <= 1) return null;

  const disabled = `${adminQuietButton} pointer-events-none opacity-40`;

  return (
    <nav aria-label="ページ送り" className="flex items-center justify-center gap-6">
      {page > 1 ? (
        <Link href={hrefFor(page - 1)} className={adminQuietButton}>
          ‹ 前へ
        </Link>
      ) : (
        <span className={disabled}>‹ 前へ</span>
      )}
      <span className="text-sm text-gray-500 tabular-nums">
        {page} / {lastPage} ページ
      </span>
      {page < lastPage ? (
        <Link href={hrefFor(page + 1)} className={adminQuietButton}>
          次へ ›
        </Link>
      ) : (
        <span className={disabled}>次へ ›</span>
      )}
    </nav>
  );
}
