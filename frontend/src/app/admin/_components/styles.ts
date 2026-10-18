// 管理画面でよく使う見た目（Tailwind のクラス）。
// 患者側（components/ui.ts）はスマートフォンで押しやすい横幅いっぱいのボタンだが、管理画面は PC で並べて使うので幅を中身に合わせる

const buttonBase =
  "inline-flex min-h-10 items-center justify-center rounded-md px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50";

/** メインのボタン（緑） */
export const adminPrimaryButton = `${buttonBase} bg-brand text-white hover:bg-brand-dark`;

/** サブのボタン（白） */
export const adminSecondaryButton = `${buttonBase} border border-gray-300 bg-white font-normal text-gray-700 hover:bg-gray-50`;

/** キャンセルなど、慎重に押してほしいボタン（赤） */
export const adminDangerButton = `${buttonBase} border border-red-300 bg-white text-red-700 hover:bg-red-50`;

/** 入力欄 */
export const textInput =
  "block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-light";

/** 白い囲み */
export const panel = "rounded-lg border border-gray-200 bg-white";
