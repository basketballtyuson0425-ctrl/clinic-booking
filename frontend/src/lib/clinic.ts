// 接骨院の情報（.env.local で設定する。NEXT_PUBLIC_ で始まる値はブラウザ用のプログラムにも埋め込まれる）
export const clinic = {
  name: process.env.NEXT_PUBLIC_CLINIC_NAME ?? "",
  phone: process.env.NEXT_PUBLIC_CLINIC_PHONE ?? "",
  address: process.env.NEXT_PUBLIC_CLINIC_ADDRESS ?? "",
};
