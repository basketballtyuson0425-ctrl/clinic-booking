// 何度も使う見た目（Tailwind のクラス）をまとめたもの

/** メインのボタン（緑） */
export const primaryButton =
  "block min-h-12 w-full rounded-lg bg-brand px-4 py-3 text-center text-base font-bold text-white hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50";

/** サブのボタン（白） */
export const secondaryButton =
  "block min-h-12 w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-center text-base text-gray-700 hover:bg-gray-50";

/** エラーや注意のメッセージ枠 */
export const alertBox = "rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800";

/** 取り消しなど、慎重に押してほしいボタン（赤） */
export const dangerButton =
  "block min-h-12 w-full rounded-lg border border-red-300 bg-white px-4 py-3 text-center text-base font-bold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50";

/** 完了などのお知らせ枠 */
export const successBox = "rounded-lg bg-brand-light px-4 py-3 text-sm text-brand-dark";
