<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * メールの送信結果。送信に失敗しても予約は成立させ、院長がここで失敗を確認できるようにする。
     */
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            // 予約が自動削除されたら、ログ（送信先メールアドレスを含む）も一緒に削除する
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);    // App\Enums\MailType
            $table->string('to_email', 255);
            $table->string('status', 10);  // App\Enums\MailStatus
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
