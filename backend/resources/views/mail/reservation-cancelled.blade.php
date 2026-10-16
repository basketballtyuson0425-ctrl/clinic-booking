{!! $reservation->patient_name !!} 様

{!! config('clinic.name') !!}です。ご予約のキャンセルを承りました。

■ キャンセルしたご予約
ご予約日時　：{!! $reservation->dateTimeLabel() !!}
お名前　　　：{!! $reservation->patient_name !!} 様

またのご来院をお待ちしております。
新しくご予約される場合は、下記のページからお願いします。
{!! $reserveUrl !!}

@include('mail._footer')
{{-- テキストメールなので {!! !!} を使う（理由は reservation-received.blade.php を参照） --}}
