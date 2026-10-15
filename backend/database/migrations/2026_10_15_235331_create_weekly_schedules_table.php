<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 曜日ごとの基本の営業時間（7行）。
     * 日付ごとの個別設定がない日は、この表の設定に従う。
     */
    public function up(): void
    {
        Schema::create('weekly_schedules', function (Blueprint $table) {
            $table->id();
            // 0=日曜〜6=土曜（Carbon の dayOfWeek と同じ番号）。曜日ごとに1行だけ
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->boolean('is_closed')->default(false);
            // 前半枠・後半枠。片方だけ休みにする場合は NULL にする
            $table->time('am_start')->nullable();
            $table->time('am_end')->nullable();
            $table->time('pm_start')->nullable();
            $table->time('pm_end')->nullable();
            // 休診にする「第N週」をカンマ区切りで持つ（例："1,3" = 第1・第3週は休診）
            $table->string('closed_weeks_of_month')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_schedules');
    }
};
