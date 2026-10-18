<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HasPatientFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 院長が管理画面から予約を登録するときの入力チェック（電話予約など。メールアドレスは任意）
 */
class CreateReservationRequest extends FormRequest
{
    use HasPatientFields;

    /**
     * ログインの確認はルートの auth:sanctum で済んでいる
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
        return [
            ...$this->patientRules(),
            'start_at' => ['required', 'date_format:Y-m-d H:i'],
            // 確認メールを送るか（画面のチェックボックス）。送り忘れ・送りすぎを防ぐため、画面から必ず指定させる
            'send_mail' => ['required', 'boolean'],
        ];
    }
}
