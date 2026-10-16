<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 予約管理ページからの変更の入力チェック。
 * 変更できるのは予約日時と施術希望内容だけ（要件 12）。送られてきた項目だけを変更する。
 */
class UpdateManagedReservationRequest extends FormRequest
{
    /**
     * 予約管理 URL の鍵を知っていることが本人の証明になる（鍵の確認はコントローラーで行う）
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // sometimes：送られてきたときだけチェックする
            'start_at' => ['sometimes', 'required', 'date_format:Y-m-d H:i'],
            'treatment_request' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * どちらも送られてこなかったら、エラーにする
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->has('start_at') && ! $this->has('treatment_request')) {
                    $validator->errors()->add('start_at', '変更する内容を指定してください。');
                }
            },
        ];
    }
}
