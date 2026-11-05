const STEPS = ["日付", "時間", "入力", "確認", "完了"];

type Props = {
  current: number; // 今の段階（0〜4）
};

/**
 * 画面上部の「1 日付 › 2 時間 › …」の進み具合。
 * 丸い番号を並べる形にはせず、文字だけで並べて、今の段階だけを緑の太字と下線で示す（色だけに頼らない）
 */
export default function StepIndicator({ current }: Props) {
  return (
    <ol className="mb-6 flex flex-wrap items-center gap-x-1.5 text-sm" aria-label="予約の手順">
      {STEPS.map((label, index) => {
        const isCurrent = index === current;
        const isDone = index < current;
        return (
          <li key={label} className="flex items-center gap-1.5" aria-current={isCurrent ? "step" : undefined}>
            {index > 0 && (
              <span aria-hidden="true" className="text-gray-300">
                ›
              </span>
            )}
            <span
              className={
                isCurrent
                  ? "border-b-2 border-brand font-bold text-brand-dark"
                  : isDone
                    ? "border-b-2 border-transparent text-gray-700"
                    : "border-b-2 border-transparent text-gray-500"
              }
            >
              <span className="tabular-nums">{index + 1}</span> {label}
            </span>
          </li>
        );
      })}
    </ol>
  );
}
