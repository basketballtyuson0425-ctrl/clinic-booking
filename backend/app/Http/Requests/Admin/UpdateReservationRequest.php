<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HasPatientFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 院長が予約の内容を変更するときの入力チェック。
 * 院長は予約日時・患者情報・施術希望内容のすべてを変更できる。送られてきた項目だけを変更する
 */
class UpdateReservationRequest extends FormRequest
{
    use HasPatientFields;

    private const FIELDS = ['start_at', 'patient_name', 'patient_phone', 'patient_email', 'visit_type', 'treatment_request'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->tidyPatientFields();
    }

    public function rules(): array
    {
        $rules = [
            ...$this->patientRules(),
            'start_at' => ['required', 'date_format:Y-m-d H:i'],
        ];

        // sometimes：送られてきた項目だけをチェックする
        $rules = array_map(fn (array $fieldRules) => ['sometimes', ...$fieldRules], $rules);

        return [
            ...$rules,
            // 日時を変えたときに、お知らせメールを送るか
            'send_mail' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * 変更する項目が1つも送られてこなかったら、エラーにする
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->hasAny(self::FIELDS)) {
                    $validator->errors()->add('start_at', '変更する内容を指定してください。');
                }
            },
        ];
    }
}
