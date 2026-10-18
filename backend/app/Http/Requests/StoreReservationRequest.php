<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPatientFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 患者のネット予約の入力チェック。
 * 画面側のチェックはすり抜けられるため、サーバー側で必ずここを通す。
 */
class StoreReservationRequest extends FormRequest
{
    use HasPatientFields;

    /**
     * 予約はログインなしで行えるので、誰でも送信してよい
     */
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
        $rules = $this->patientRules();
        // 患者が入力するときは、打ち間違いを防ぐため確認用の欄と一致させる
        $rules['patient_email'][] = 'confirmed';

        return [
            ...$rules,
            'start_at' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }
}
