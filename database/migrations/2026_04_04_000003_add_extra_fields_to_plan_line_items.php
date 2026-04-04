<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_line_items', function (Blueprint $table) {
            $table->integer('live_days')->nullable()->after('end_date');
            $table->integer('daily_spots')->nullable()->after('unit_cost');
            $table->bigInteger('est_impression_day')->nullable()->after('est_impression');
            $table->bigInteger('est_ad_spot')->nullable()->after('est_impression_day');
        });
    }

    public function down(): void
    {
        Schema::table('plan_line_items', function (Blueprint $table) {
            $table->dropColumn(['live_days', 'daily_spots', 'est_impression_day', 'est_ad_spot']);
        });
    }
};
