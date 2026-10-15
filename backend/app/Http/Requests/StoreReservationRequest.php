<?php

namespace App\Http\Requests;

use App\Enums\VisitType;
use App\Models\Reservation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 患者のネット予約の入力チェック。
 * 画面側のチェックはすり抜けられるため、サーバー側で必ずここを通す。
 */
class StoreReservationRequest extends FormRequest
{
    /**
     * 予約はログインなしで行えるので、誰でも送信してよい
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * チェックの前に入力を整える（全角スペース・全角英数字を半角にし、前後の空白を取り除く）
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'patient_name' => $this->tidy($this->input('patient_name'), 's'),
            'patient_phone' => $this->tidy($this->input('patient_phone'), 'as'),
            'patient_email' => $this->tidy($this->input('patient_email'), 'as'),
            'patient_email_confirmation' => $this->tidy($this->input('patient_email_confirmation'), 'as'),
        ]);
    }

    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:100'],
            'patient_phone' => [
                'bail', // 最初に引っかかったルールでチェックを止める（文字以外が来たときに下の関数へ進ませない）
                'required',
                'string',
                'max:20',
                'regex:/^0[0-9-]+$/',
                // 数字だけにしたときに10〜11桁であること（固定電話・携帯電話）
                function (string $attribute, mixed $value, Closure $fail) {
                    $digits = strlen(Reservation::normalizePhone($value));
                    if ($digits < 10 || $digits > 11) {
                        $fail('電話番号の桁数が正しくありません。');
                    }
                },
            ],
            'patient_email' => ['nullable', 'string', 'email', 'max:255', 'confirmed'],
            'visit_type' => ['required', Rule::enum(VisitType::class)],
            'treatment_request' => ['nullable', 'string', 'max:500'],
            'start_at' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }

    /**
     * 文字列なら全角→半角の変換（mb_convert_kana のオプション）と前後の空白の除去を行う。空になったら null にする
     */
    private function tidy(mixed $value, string $kanaOption): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim(mb_convert_kana($value, $kanaOption));

        return $value === '' ? null : $value;
    }
}
