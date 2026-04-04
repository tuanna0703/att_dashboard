<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Add currency to bookings ──────────────────────────────────────────
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('currency', 10)->default('VND')->after('total_budget');
        });

        // ── Redesign booking_line_items ───────────────────────────────────────
        Schema::disableForeignKeyConstraints();
        Schema::drop('booking_line_items');
        Schema::enableForeignKeyConstraints();

        Schema::create('booking_line_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('plan_line_item_id')
                ->nullable()
                ->constrained('plan_line_items')
                ->nullOnDelete();

            // ── Snapshot từ PlanLineItem ──────────────────────────────────────
            $table->string('format', 10)->nullable();
            $table->json('targeting')->nullable();           // array of AdNetwork IDs
            $table->json('targeting_names')->nullable();     // array of AdNetwork names (snapshot)

            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('live_days')->nullable();

            $table->string('unit', 10)->default('cpm');     // cpm / cpd / io
            $table->decimal('guaranteed_units', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->unsignedInteger('daily_spots')->nullable();

            // line_budget = doanh thu Finance kiểm soát
            $table->decimal('line_budget', 15, 2)->default(0);

            // Est KPI
            $table->unsignedBigInteger('est_impression')->nullable();
            $table->unsignedBigInteger('est_impression_day')->nullable();
            $table->unsignedBigInteger('est_ad_spot')->nullable();

            // ── Media buying tracking ─────────────────────────────────────────
            $table->enum('buying_status', ['pending', 'partial', 'fully_bought', 'cancelled'])
                ->default('pending');

            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_line_items');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
