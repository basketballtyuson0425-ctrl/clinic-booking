{!! $reservation->patient_name !!} 様

{!! config('clinic.name') !!}です。ご予約の変更を承りました。

■ 変更後のご予約内容
ご予約日時　：{!! $reservation->dateTimeLabel() !!}
お名前　　　：{!! $reservation->patient_name !!} 様
初回／再来　：{!! $reservation->visit_type->label() !!}
施術希望内容：{!! $reservation->treatment_request ?? '（なし）' !!}

■ 予約の確認・変更・キャンセル
引き続き、下記の予約管理ページから、予約時刻の1時間前まで変更・キャンセルができます。
{!! $manageUrl !!}
※ この URL はご本人様専用です。他の方には教えないでください。

予約時刻の1時間前を過ぎてからの変更・キャンセルは、お電話でご連絡ください。

@include('mail._footer')
{{-- テキストメールなので {!! !!} を使う（理由は reservation-received.blade.php を参照） --}}
