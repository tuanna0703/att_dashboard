<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brief_line_items', function (Blueprint $table) {
            // Location
            $table->string('city')->nullable()->after('targeting');
            $table->unsignedInteger('qty_location')->nullable()->after('city');
            $table->unsignedInteger('qty_screen')->nullable()->after('qty_location');

            // Airing
            $table->string('time_from', 5)->nullable()->after('end_date');
            $table->string('time_to', 5)->nullable()->after('time_from');
            $table->decimal('total_hours', 5, 1)->nullable()->after('time_to');
            $table->decimal('sov', 5, 2)->nullable()->after('total_hours');
            $table->unsignedInteger('duration_seconds')->nullable()->after('sov');
            $table->decimal('frequency_minutes', 5, 1)->nullable()->after('duration_seconds');

            // Buying weeks
            $table->unsignedInteger('buy_weeks')->nullable()->after('live_days');
            $table->unsignedInteger('foc_weeks')->default(0)->after('buy_weeks');
            $table->unsignedInteger('total_weeks')->nullable()->after('foc_weeks');

            // Pricing
            $table->decimal('gross_amount', 18, 2)->default(0)->after('line_budget');
            $table->decimal('vat_rate', 5, 2)->default(8)->after('gross_amount');

            // KPI
            $table->unsignedInteger('kpi_multiplier')->default(1)->after('est_ad_spot');
        });
    }

    public function down(): void
    {
        Schema::table('brief_line_items', function (Blueprint $table) {
            $table->dropColumn([
                'city', 'qty_location', 'qty_screen',
                'time_from', 'time_to', 'total_hours', 'sov', 'duration_seconds', 'frequency_minutes',
                'buy_weeks', 'foc_weeks', 'total_weeks',
                'gross_amount', 'vat_rate',
                'kpi_multiplier',
            ]);
        });
    }
};
