<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * 営業時間（休診・前半枠・後半枠）の入力チェック。日付ごとの設定と、曜日ごとの基本設定で共通に使う
 */
trait ValidatesBusinessHours
{
    // 30分単位の時刻（00:00〜23:30）。予約枠が30分なので、半端な時刻だと枠を作れない
    private const TIME_PATTERN = '/^([01][0-9]|2[0-3]):(00|30)$/';

    /**
     * @param  string  $prefix  項目名の前に付ける文字（曜日ごとの設定なら "schedules.0." など）
     * @return array<string, array<int, string>>
     */
    protected function hoursRules(string $prefix = ''): array
    {
        $time = ['nullable', 'string', 'regex:'.self::TIME_PATTERN];

        return [
            "{$prefix}is_closed" => ['required', 'boolean'],
            "{$prefix}am_start" => $time,
            "{$prefix}am_end" => $time,
            "{$prefix}pm_start" => $time,
            "{$prefix}pm_end" => $time,
        ];
    }

    /**
     * 項目どうしの関係をチェックする（1つずつのルールでは確かめられないもの）
     *
     * @param  array<string, mixed>  $hours  is_closed・am_start・am_end・pm_start・pm_end
     */
    protected function checkHours(Validator $validator, array $hours, string $prefix = ''): void
    {
        // 休診にする日は、時刻を見ない（保存もしない）
        if (filter_var($hours['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $periods = ['am' => '前半', 'pm' => '後半'];
        $filled = [];
        $restCount = 0; // 休みにした枠の数
        foreach ($periods as $period => $label) {
            $start = $hours["{$period}_start"] ?? null;
            $end = $hours["{$period}_end"] ?? null;

            if ($start === null && $end === null) {
                $restCount++; // この枠は休み

                continue;
            }
            if ($start === null || $end === null) {
                $validator->errors()->add("{$prefix}{$period}_start", "{$label}の開始と終了を、両方入力してください。");

                continue;
            }
            // "09:00" < "13:00" のように、2桁そろえた時刻は文字のまま大小を比べられる
            if ($start >= $end) {
                $validator->errors()->add("{$prefix}{$period}_end", "{$label}の終了は、開始より後の時刻にしてください。");

                continue;
            }
            $filled[$period] = ['start' => $start, 'end' => $end];
        }

        if ($restCount === 2) {
            $validator->errors()->add("{$prefix}am_start", '前半か後半の少なくとも一方を入力してください（休みにする場合は「休診にする」を選んでください）。');
        }

        if (isset($filled['am'], $filled['pm']) && $filled['am']['end'] > $filled['pm']['start']) {
            $validator->errors()->add("{$prefix}pm_start", '後半の開始は、前半の終了と同じか、それより後の時刻にしてください。');
        }
    }
}
