<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesBusinessHours;
use App\Models\BusinessDayOverride;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 日付ごとの営業時間の変更（保存・影響の確認）の入力チェック
 */
class BusinessDayRequest extends FormRequest
{
    use ValidatesBusinessHours;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 影響の確認で「通常設定に戻す」場合は、営業時間を送らない
        if ($this->boolean('reset')) {
            return ['reset' => ['boolean']];
        }

        return [
            ...$this->hoursRules(),
            // 予約がある日の変更を、院長が確認したか
            'confirmed' => ['sometimes', 'boolean'],
            // 影響のある予約の患者に、お知らせメールを送るか
            'send_mail' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * 時刻の形のエラー（このリクエストで regex を使うのは時刻だけ）
     */
    public function messages(): array
    {
        return [
            'regex' => ':attributeは30分単位（例：9:00、9:30）で入力してください。',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // 1つずつのチェックで引っかかったときは、組み合わせのチェックはしない（同じ欄にエラーが重ならないように）
                if ($this->boolean('reset') || $validator->errors()->isNotEmpty()) {
                    return;
                }
                $this->checkHours($validator, $this->all());
            },
        ];
    }

    /**
     * 入力から、保存前の個別設定を作る（休診の日は時刻を空にする）
     */
    public function toOverride(string $date): BusinessDayOverride
    {
        $isClosed = $this->boolean('is_closed');
        // DB には "09:00:00" の形で保存する（曜日ごとの設定と同じ形）
        $time = fn (string $key) => $isClosed || $this->input($key) === null ? null : $this->input($key).':00';

        return new BusinessDayOverride([
            'date' => $date,
            'is_closed' => $isClosed,
            'am_start' => $time('am_start'),
            'am_end' => $time('am_end'),
            'pm_start' => $time('pm_start'),
            'pm_end' => $time('pm_end'),
        ]);
    }
}
