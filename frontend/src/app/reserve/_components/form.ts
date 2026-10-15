// 入力フォームの値の型と、画面側の入力チェック。
// 画面側のチェックは「その場で気づいてもらうため」のもの。最終的なチェックはサーバー（Laravel）が行う。

import type { ReservationInput, VisitType } from "@/lib/api";

/** 入力中のフォームの値 */
export type ReservationForm = {
  patientName: string;
  patientPhone: string;
  patientEmail: string;
  patientEmailConfirmation: string;
  visitType: VisitType | "";
  treatmentRequest: string;
};

/** 項目ごとのエラーメッセージ */
export type FormErrors = Partial<Record<keyof ReservationForm, string>>;

export const emptyForm: ReservationForm = {
  patientName: "",
  patientPhone: "",
  patientEmail: "",
  patientEmailConfirmation: "",
  visitType: "",
  treatmentRequest: "",
};

/** サーバーのエラーの項目名 → フォームの項目名 */
export const SERVER_FIELD_TO_FORM: Record<string, keyof ReservationForm> = {
  patient_name: "patientName",
  patient_phone: "patientPhone",
  patient_email: "patientEmail",
  visit_type: "visitType",
  treatment_request: "treatmentRequest",
};

/** 全角の英数字・記号を半角にする（例："０９０" → "090"、"ｔａｒｏ＠" → "taro@"） */
function toHalfWidth(value: string): string {
  return value
    .replace(/[！-～]/g, (char) => String.fromCharCode(char.charCodeAt(0) - 0xfee0))
    .replace(/　/g, " ");
}

/** 電話番号を半角にそろえる。長音「ー」などハイフンに似た文字もハイフンにする */
export function normalizePhone(value: string): string {
  return toHalfWidth(value.trim()).replace(/[ー―‐−]/g, "-");
}

export function normalizeEmail(value: string): string {
  return toHalfWidth(value.trim());
}

/** 文字数（絵文字なども1文字として数える） */
function lengthOf(value: string): number {
  return [...value].length;
}

export function validateForm(form: ReservationForm): FormErrors {
  const errors: FormErrors = {};

  const name = form.patientName.trim();
  if (name === "") {
    errors.patientName = "氏名を入力してください。";
  } else if (lengthOf(name) > 100) {
    errors.patientName = "氏名は100文字以内で入力してください。";
  }

  const phone = normalizePhone(form.patientPhone);
  const digits = phone.replace(/-/g, "").length;
  if (phone === "") {
    errors.patientPhone = "電話番号を入力してください。";
  } else if (!/^0[0-9-]+$/.test(phone)) {
    errors.patientPhone = "電話番号は半角数字とハイフンで、0から入力してください。";
  } else if (digits < 10 || digits > 11) {
    errors.patientPhone = "電話番号の桁数が正しくありません。";
  }

  if (form.visitType === "") {
    errors.visitType = "初回か再来かを選択してください。";
  }

  const email = normalizeEmail(form.patientEmail);
  if (email !== "") {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || lengthOf(email) > 255) {
      errors.patientEmail = "メールアドレスの形式が正しくありません。";
    } else if (normalizeEmail(form.patientEmailConfirmation) !== email) {
      errors.patientEmailConfirmation = "メールアドレスが確認用と一致しません。";
    }
  }

  if (lengthOf(form.treatmentRequest.trim()) > 500) {
    errors.treatmentRequest = "施術希望内容は500文字以内で入力してください。";
  }

  return errors;
}

/** フォームの値を、API に送る形に変換する */
export function toReservationInput(form: ReservationForm, startAt: string): ReservationInput {
  const email = normalizeEmail(form.patientEmail);
  return {
    patient_name: form.patientName.trim(),
    patient_phone: normalizePhone(form.patientPhone),
    patient_email: email,
    patient_email_confirmation: email === "" ? "" : normalizeEmail(form.patientEmailConfirmation),
    visit_type: form.visitType as VisitType,
    treatment_request: form.treatmentRequest.trim(),
    start_at: startAt,
  };
}
