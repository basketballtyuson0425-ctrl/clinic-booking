<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 予約一覧の絞り込み条件のチェック（URL の ?date=…&name=… の部分）
 */
class ListReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 全角の数字・英字・スペースを半角にそろえる（保存した name_key・phone_key と同じ形で探すため）
     */
    protected function prepareForValidation(): void
    {
        foreach (['name' => 's', 'phone' => 'as'] as $key => $option) {
            if (is_string($this->query($key))) {
                $value = trim(mb_convert_kana($this->query($key), $option));
                $this->merge([$key => $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9-]+$/'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'include_past' => ['nullable', 'boolean'],
            'needs_contact' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => '電話番号は数字とハイフンで入力してください。',
        ];
    }
}
