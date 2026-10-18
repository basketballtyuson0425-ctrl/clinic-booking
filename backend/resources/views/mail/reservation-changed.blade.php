{!! $reservation->patient_name !!} 様

@if ($manageUrl === null)
{!! config('clinic.name') !!}です。ご予約の日時を、下記のとおり変更いたしました。
@else
{!! config('clinic.name') !!}です。ご予約の変更を承りました。
@endif

■ 変更後のご予約内容
ご予約日時　：{!! $reservation->dateTimeLabel() !!}
お名前　　　：{!! $reservation->patient_name !!} 様
初回／再来　：{!! $reservation->visit_type->label() !!}
施術希望内容：{!! $reservation->treatment_request ?? '（なし）' !!}

■ 予約の確認・変更・キャンセル
@if ($manageUrl !== null)
引き続き、下記の予約管理ページから、予約時刻の1時間前まで変更・キャンセルができます。
{!! $manageUrl !!}
※ この URL はご本人様専用です。他の方には教えないでください。
@elseif ($reservation->manage_token_hash !== null)
以前お送りしたメールの予約管理ページの URL から、引き続き、予約時刻の1時間前まで変更・キャンセルができます。
@endif

予約時刻の1時間前を過ぎてからの変更・キャンセル（予約管理ページの URL がお手元にない場合も）は、お電話でご連絡ください。

@include('mail._footer')
{{-- テキストメールなので {!! !!} を使う（理由は reservation-received.blade.php を参照） --}}
{{-- 院長が変更したとき（$manageUrl が null）は、DB に鍵のハッシュ値しかないので URL を載せられない。患者の手元にある以前の URL はそのまま使える --}}
