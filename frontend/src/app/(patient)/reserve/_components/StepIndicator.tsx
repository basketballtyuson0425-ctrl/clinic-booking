const STEPS = ["日付", "時間", "入力", "確認", "完了"];

type Props = {
  current: number; // 今の段階（0〜4）
};

/** 画面上部の「① 日付 ② 時間 …」の進み具合 */
export default function StepIndicator({ current }: Props) {
  return (
    <ol className="mb-6 flex justify-between text-xs" aria-label="予約の手順">
      {STEPS.map((label, index) => {
        const isCurrent = index === current;
        const isDone = index < current;
        return (
          <li
            key={label}
            className="flex flex-1 flex-col items-center gap-1"
            aria-current={isCurrent ? "step" : undefined}
          >
            <span
              className={`flex h-7 w-7 items-center justify-center rounded-full font-bold ${
                isCurrent ? "bg-brand text-white" : isDone ? "bg-brand-light text-brand-dark" : "bg-gray-200 text-gray-500"
              }`}
            >
              {index + 1}
            </span>
            <span className={isCurrent ? "font-bold text-brand-dark" : "text-gray-500"}>{label}</span>
          </li>
        );
      })}
    </ol>
  );
}
