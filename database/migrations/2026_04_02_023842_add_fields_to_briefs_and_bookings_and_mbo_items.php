<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── briefs: thêm objective + target_audience ──────────────────────────
        Schema::table('briefs', function (Blueprint $table) {
            $table->text('objective')->nullable()->after('campaign_name');
            $table->text('target_audience')->nullable()->after('objective');
        });

        // ── bookings: thêm tax + grand_total ─────────────────────────────────
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('tax_pct', 5, 2)->default(10)->after('total_budget');
            $table->decimal('tax_amount', 18, 2)->default(0)->after('tax_pct');
            $table->decimal('grand_total', 18, 2)->nullable()->after('tax_amount');
        });

        // ── media_buying_order_items: thêm screen_id (giữ ad_network_id cũ) ──
        Schema::table('media_buying_order_items', function (Blueprint $table) {
            $table->foreignId('screen_id')
                  ->nullable()
                  ->after('ad_network_id')
                  ->constrained('screens')
                  ->nullOnDelete();
            $table->string('location_city', 100)->nullable()->after('screen_id');
            $table->date('start_date')->nullable()->after('location_city');
            $table->date('end_date')->nullable()->after('start_date');
            $table->integer('spot_duration')->default(15)->after('end_date');
            $table->string('pricing_model', 20)->default('fixed')->after('spot_duration');
            $table->decimal('cpm', 10, 2)->nullable()->after('pricing_model');
        });
    }

    public function down(): void
    {
        Schema::table('media_buying_order_items', function (Blueprint $table) {
            $table->dropForeign(['screen_id']);
            $table->dropColumn(['screen_id', 'location_city', 'start_date', 'end_date', 'spot_duration', 'pricing_model', 'cpm']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['tax_pct', 'tax_amount', 'grand_total']);
        });

        Schema::table('briefs', function (Blueprint $table) {
            $table->dropColumn(['objective', 'target_audience']);
        });
    }
};
