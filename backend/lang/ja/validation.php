<?php

/*
 * 入力チェック（バリデーション）のエラーメッセージ（日本語）
 * このアプリで使うルールだけを定義している。ここにないルールは英語のメッセージになる。
 */
return [
    'boolean' => ':attributeの指定が正しくありません。',
    'confirmed' => ':attributeが確認用と一致しません。',
    'date_format' => ':attributeの形式が正しくありません。',
    'email' => ':attributeの形式が正しくありません。',
    'array' => ':attributeの形式が正しくありません。',
    'between' => [
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
    ],
    'distinct' => ':attributeが重複しています。',
    'enum' => ':attributeの選択が正しくありません。',
    'integer' => ':attributeは整数で指定してください。',
    'present' => ':attributeを指定してください。',
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください。',
    ],
    'min' => [
        'numeric' => ':attributeは:min以上で指定してください。',
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeを入力してください。',
    'size' => [
        'array' => ':attributeは:size件で指定してください。',
    ],
    'string' => ':attributeは文字で入力してください。',

    // 項目ごとに文言を変えたいもの
    'custom' => [
        'patient_phone' => [
            'regex' => '電話番号は半角数字とハイフンで、0から入力してください。',
        ],
        'visit_type' => [
            'required' => '初回か再来かを選択してください。',
        ],
        'start_at' => [
            'required' => '予約日時を選択してください。',
        ],
        'send_mail' => [
            'required' => 'メールを送るかどうかを指定してください。',
        ],
    ],

    // :attribute の部分に入る項目名
    'attributes' => [
        'patient_name' => '氏名',
        'patient_phone' => '電話番号',
        'patient_email' => 'メールアドレス',
        'visit_type' => '初回／再来',
        'treatment_request' => '施術希望内容',
        'start_at' => '予約日時',
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        // 管理画面
        'send_mail' => 'メールの送信',
        'date' => '日付',
        'phone' => '電話番号',
        'status' => '状態',
        'include_past' => '過去の予約の表示',
        'failed_only' => '送信失敗の絞り込み',
        'page' => 'ページ',
        'needs_contact' => '要連絡の絞り込み',
        'month' => '月',
        // 営業時間
        'is_closed' => '休診の指定',
        'am_start' => '前半の開始',
        'am_end' => '前半の終了',
        'pm_start' => '後半の開始',
        'pm_end' => '後半の終了',
        'confirmed' => '確認の指定',
        'schedules' => '曜日ごとの設定',
        'schedules.*.day_of_week' => '曜日',
        'schedules.*.is_closed' => '休診の指定',
        'schedules.*.am_start' => '前半の開始',
        'schedules.*.am_end' => '前半の終了',
        'schedules.*.pm_start' => '後半の開始',
        'schedules.*.pm_end' => '後半の終了',
        'schedules.*.closed_weeks' => '休診にする週',
        'schedules.*.closed_weeks.*' => '休診にする週',
    ],
];
