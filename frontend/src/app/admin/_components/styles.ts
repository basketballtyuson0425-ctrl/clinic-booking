// 管理画面でよく使う見た目（Tailwind のクラス）。
// 患者側（components/ui.ts）はスマートフォンで押しやすい横幅いっぱいのボタンだが、管理画面は PC で並べて使うので幅を中身に合わせる。
// 管理画面では、内容を箱（枠線・背景・角丸）で囲まず、罫線で区切る。囲みが重なると、どこが大事か分かりにくくなるため

const buttonBase =
  "inline-flex min-h-10 items-center justify-center rounded-md px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50";

/** メインのボタン（緑） */
export const adminPrimaryButton = `${buttonBase} bg-brand text-white hover:bg-brand-dark`;

/** サブのボタン（白） */
export const adminSecondaryButton = `${buttonBase} border border-gray-300 bg-white font-normal text-gray-700 hover:bg-gray-50`;

/** キャンセルなど、慎重に押してほしいボタン（赤） */
export const adminDangerButton = `${buttonBase} border border-red-300 bg-white text-red-700 hover:bg-red-50`;

/** 文字だけの控えめなボタン（「条件をクリア」・月の切り替え・ページ送りなど） */
export const adminQuietButton =
  "inline-flex min-h-10 items-center px-1 text-sm text-gray-600 underline-offset-4 hover:text-gray-900 hover:underline disabled:cursor-not-allowed disabled:opacity-50";

/** 入力欄（枠付き。新規登録・修正など、入力が主な画面で使う） */
export const textInput =
  "block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-light";

/** 下線だけの入力欄（一覧の絞り込みなど、画面の脇役の入力で使う） */
export const underlineInput =
  "block h-10 w-full rounded-none border-0 border-b border-gray-300 bg-transparent px-0 text-base hover:border-gray-500 focus:border-brand focus:shadow-[0_1px_0_0_var(--color-brand)] focus:outline-none";

/** 要連絡などの注意。箱で囲まず、左の朱色の線で目立たせる */
export const noticeLine = "border-l-2 border-alert pl-4 text-sm";
