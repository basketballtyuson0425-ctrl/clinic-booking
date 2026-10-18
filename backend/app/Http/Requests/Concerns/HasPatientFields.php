<?php

namespace App\Http\Requests\Concerns;

use App\Enums\VisitType;
use App\Models\Reservation;
use Closure;
use Illuminate\Validation\Rule;

/**
 * 患者情報（氏名・電話番号・メールアドレス・初回／再来・施術希望内容）の入力チェック。
 * 患者のネット予約と、院長の登録・変更で同じルールを使う
 */
trait HasPatientFields
{
    /**
     * チェックの前に入力を整える（全角スペース・全角英数字を半角にし、前後の空白を取り除く）。
     * 送られてきた項目だけを整える（院長の変更では、送られていない項目は変えないため）
     */
    protected function tidyPatientFields(): void
    {
        // mb_convert_kana のオプション（s：全角スペース→半角、a：全角英数字→半角）
        $options = [
            'patient_name' => 's',
            'patient_phone' => 'as',
            'patient_email' => 'as',
            'patient_email_confirmation' => 'as',
        ];

        $tidied = [];
        foreach ($options as $key => $option) {
            if ($this->has($key)) {
                $tidied[$key] = $this->tidy($this->input($key), $option);
            }
        }
        $this->merge($tidied);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function patientRules(): array
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
            'patient_email' => ['nullable', 'string', 'email', 'max:255'],
            'visit_type' => ['required', Rule::enum(VisitType::class)],
            'treatment_request' => ['nullable', 'string', 'max:500'],
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
