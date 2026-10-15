<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 日付ごとの個別設定（例外の日だけ記録する）。
     * 「通常設定に戻す」は、この表の行を削除して実現する。
     */
    public function up(): void
    {
        Schema::create('business_day_overrides', function (Blueprint $table) {
            $table->id();
            // 同じ日に個別設定が2つできないよう、一意にする
            $table->date('date')->unique();
            $table->boolean('is_closed')->default(false);
            $table->time('am_start')->nullable();
            $table->time('am_end')->nullable();
            $table->time('pm_start')->nullable();
            $table->time('pm_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_day_overrides');
    }
};
