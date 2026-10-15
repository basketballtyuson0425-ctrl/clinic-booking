<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // 患者情報
            $table->string('patient_name', 100);
            $table->string('patient_phone', 20);
            $table->string('patient_email', 255)->nullable();
            $table->string('visit_type', 10);              // App\Enums\VisitType
            $table->text('treatment_request')->nullable();

            // 予約日時。timestamp 型はタイムゾーン変換や 2038 年問題があるため datetime 型にする
            $table->dateTime('start_at')->index();
            $table->dateTime('end_at')->index();           // 予約終了から1年後の自動削除で使う

            $table->string('status', 20)->default('reserved')->index(); // App\Enums\ReservationStatus
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancelled_by', 10)->nullable(); // App\Enums\CancelledBy
            $table->string('source', 10);                   // App\Enums\ReservationSource

            // 予約管理 URL のトークンは平文で保存せず、SHA-256 のハッシュ値だけを保存する
            $table->char('manage_token_hash', 64)->nullable()->unique();

            // 同一人物の判定用に、表記ゆれをそろえた氏名・電話番号
            $table->string('name_key', 100);
            $table->string('phone_key', 20);

            // 予約中のときだけ値が入り、キャンセルすると NULL になる列。
            // NULL どうしは重複とみなされないので、キャンセル済みの予約は制約に影響しない。
            // 1つの枠に予約中の予約が2件入るのを、DB の一意制約で確実に防ぐ
            $table->dateTime('active_start_at')->nullable()->unique();
            $table->date('active_date')->nullable();

            $table->timestamps();

            // 同じ人（氏名＋電話番号）が同じ日に2件予約するのを、DB の一意制約で防ぐ
            $table->unique(['active_date', 'name_key', 'phone_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
