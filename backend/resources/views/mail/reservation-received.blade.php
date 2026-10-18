{!! $reservation->patient_name !!} 様

@if ($resent)
{!! config('clinic.name') !!}です。ご予約内容をお送りします。
以前のメールでお知らせした予約管理ページの URL は使えなくなりました。今後は、このメールの URL をご利用ください。
@elseif ($fromWeb)
{!! config('clinic.name') !!}です。ネット予約を承りました。
@else
{!! config('clinic.name') !!}です。ご予約を承りました。
@endif

■ ご予約内容
ご予約日時　：{!! $reservation->dateTimeLabel() !!}
お名前　　　：{!! $reservation->patient_name !!} 様
初回／再来　：{!! $reservation->visit_type->label() !!}
施術希望内容：{!! $reservation->treatment_request ?? '（なし）' !!}

■ 予約の確認・変更・キャンセル
下記の予約管理ページから、予約時刻の1時間前まで、予約日時と施術希望内容の変更・キャンセルができます。
{!! $manageUrl !!}
※ この URL はご本人様専用です。他の方には教えないでください。

予約時刻の1時間前を過ぎてからの変更・キャンセルは、お電話でご連絡ください。

@include('mail._footer')
{{-- テキストメールなので、{!! !!}（HTML 用の変換をしない表示）を使う。{{ }} だと「&」が「&amp;」のように表示されてしまう。HTML のページでは必ず {{ }} を使うこと --}}
