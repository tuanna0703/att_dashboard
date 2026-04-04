<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Alter plans table ──────────────────────────────────────────────
        Schema::table('plans', function (Blueprint $table) {
            // Drop FK before renaming
            $table->dropForeign(['created_by']);
            $table->renameColumn('created_by', 'adops_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            // Re-add FK with new column name
            $table->foreign('adops_id')->references('id')->on('users')->restrictOnDelete();

            // Drop duplicate Brief fields — now derived from Brief or line items
            $table->dropColumn(['campaign_name', 'start_date', 'end_date', 'cpm', 'duration_days']);
        });

        // ── 2. Drop FK from booking_line_items so we can recreate plan_line_items
        Schema::table('booking_line_items', function (Blueprint $table) {
            $table->dropForeign(['plan_line_item_id']);
        });

        // ── 3. Drop and recreate plan_line_items with collaborative schema ────
        Schema::dropIfExists('plan_line_items');

        Schema::create('plan_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();

            // Link back to the Brief line item this item is based on (nullable = AdOps added new)
            $table->foreignId('brief_line_item_id')
                  ->nullable()
                  ->constrained('brief_line_items')
                  ->nullOnDelete();

            // Ownership
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('source', ['sale', 'adops'])->default('adops');

            // KPIs — mirrors BriefLineItem fields
            $table->string('format', 100)->nullable();
            $table->json('targeting')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('unit', ['cpm', 'cpd', 'io'])->nullable();
            $table->decimal('guaranteed_units', 15, 2)->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('line_budget', 15, 2)->nullable();
            $table->bigInteger('est_impression')->nullable();

            // Collaborative status
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── 4. Re-add FK on booking_line_items ───────────────────────────────
        Schema::table('booking_line_items', function (Blueprint $table) {
            $table->foreign('plan_line_item_id')
                  ->references('id')
                  ->on('plan_line_items')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop new plan_line_items FK from bookings
        Schema::table('booking_line_items', function (Blueprint $table) {
            $table->dropForeign(['plan_line_item_id']);
        });

        // Restore old plan_line_items
        Schema::dropIfExists('plan_line_items');

        Schema::create('plan_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained('screens')->nullOnDelete();
            $table->string('venue_name');
            $table->string('venue_type', 50)->nullable();
            $table->string('location_city', 100)->nullable();
            $table->string('screen_code', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('spot_duration')->default(15);
            $table->integer('spots_per_hour')->nullable();
            $table->integer('daily_hours')->nullable();
            $table->decimal('total_spots', 10, 0)->nullable();
            $table->string('pricing_model', 20)->default('cpm');
            $table->decimal('rate_card_price', 15, 2)->nullable();
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('net_price', 15, 2)->nullable();
            $table->decimal('cpm', 10, 2)->nullable();
            $table->decimal('estimated_impressions', 15, 0)->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('booking_line_items', function (Blueprint $table) {
            $table->foreign('plan_line_item_id')
                  ->references('id')
                  ->on('plan_line_items')
                  ->nullOnDelete();
        });

        // Restore plans columns
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['adops_id']);
            $table->renameColumn('adops_id', 'created_by');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->string('campaign_name', 200)->after('version')->default('');
            $table->date('start_date')->nullable()->after('campaign_name');
            $table->date('end_date')->nullable()->after('start_date');
            $table->decimal('cpm', 18, 2)->nullable()->after('budget');
            $table->integer('duration_days')->nullable()->after('screen_count');
        });
    }
};
