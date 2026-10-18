{!! $reservation->patient_name !!} 様

{!! config('clinic.name') !!}です。
誠に申し訳ございませんが、{!! $dateLabel !!}の営業時間を変更いたしました。
お客様のご予約の時間が、変更後の営業時間から外れております。

■ 変更後の営業時間（{!! $dateLabel !!}）
@if ($schedule->isClosed)
休診
@else
前半：{!! $amLabel !!}
後半：{!! $pmLabel !!}
@endif

■ お客様のご予約
ご予約日時　：{!! $reservation->dateTimeLabel() !!}
お名前　　　：{!! $reservation->patient_name !!} 様

お手数をおかけしますが、ご予約の日時の変更について、お電話でご連絡ください。
ご予約はキャンセルされておりません。

@include('mail._footer')
{{-- テキストメールなので {!! !!} を使う（理由は reservation-received.blade.php を参照） --}}
