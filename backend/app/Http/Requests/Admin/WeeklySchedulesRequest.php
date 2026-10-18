<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesBusinessHours;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * 曜日ごとの基本の営業時間の変更（保存・影響の確認）の入力チェック。7曜日分をまとめて送る
 */
class WeeklySchedulesRequest extends FormRequest
{
    use ValidatesBusinessHours;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'size:7'],
            // 日曜（0）〜土曜（6）が1つずつ
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            ...$this->hoursRules('schedules.*.'),
            // 休診にする「第N週」（例：[1, 3]＝第1・第3）
            'schedules.*.closed_weeks' => ['present', 'array'],
            'schedules.*.closed_weeks.*' => ['integer', 'between:1,5', 'distinct'],
            'confirmed' => ['sometimes', 'boolean'],
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                foreach ($this->input('schedules') as $index => $schedule) {
                    $this->checkHours($validator, $schedule, "schedules.{$index}.");
                }
            },
        ];
    }

    /**
     * 入力から、保存前の曜日ごとの設定を作る
     *
     * @return Collection<int, WeeklySchedule> キーは曜日（0〜6）
     */
    public function toWeeklySchedules(): Collection
    {
        return collect($this->input('schedules'))
            ->mapWithKeys(function (array $schedule) {
                $isClosed = filter_var($schedule['is_closed'], FILTER_VALIDATE_BOOLEAN);
                $time = fn (string $key) => $isClosed || ($schedule[$key] ?? null) === null ? null : $schedule[$key].':00';
                $weeks = collect($schedule['closed_weeks'])->map(fn ($week) => (int) $week)->sort()->values();

                return [(int) $schedule['day_of_week'] => new WeeklySchedule([
                    'day_of_week' => (int) $schedule['day_of_week'],
                    'is_closed' => $isClosed,
                    'am_start' => $time('am_start'),
                    'am_end' => $time('am_end'),
                    'pm_start' => $time('pm_start'),
                    'pm_end' => $time('pm_end'),
                    // DB には "1,3" の形で保存する。なければ NULL
                    'closed_weeks_of_month' => $weeks->isEmpty() ? null : $weeks->implode(','),
                ])];
            });
    }
}
