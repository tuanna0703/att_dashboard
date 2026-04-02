<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Patch briefs table: add currency, drop cpm & duration_days
        Schema::table('briefs', function (Blueprint $table) {
            $table->enum('currency', ['VND', 'USD'])->default('VND')->after('budget');
            $table->dropColumn(['cpm', 'duration_days']);
        });

        // 2. Create brief_line_items
        Schema::create('brief_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained('briefs')->cascadeOnDelete();

            // Placement info
            $table->string('platform', 100)->nullable();   // Programmatic, Direct, OOH…
            $table->string('placement', 100)->nullable();  // Digital OOH, Static OOH…
            $table->string('format', 100)->nullable();     // 1TVC 15s, 30s…
            $table->string('location', 100)->nullable();   // Vietnam, HCM, HN…
            $table->text('targeting')->nullable();          // Shopping Malls: AEON MALL…

            // Date range
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('live_days')->nullable(); // computed from dates

            // Buying model
            $table->enum('unit', ['cpm', 'cpd', 'io'])->default('cpm');
            // CPM  → guaranteed_units = total impressions
            // CPD  → guaranteed_units = number of screens
            // I/O  → guaranteed_units = spots per day
            $table->unsignedBigInteger('guaranteed_units')->nullable();
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->unsignedInteger('daily_spots')->nullable(); // CPD only: spots/screen/day

            // Computed financials
            $table->decimal('line_budget', 18, 2)->nullable();

            // Est KPI
            $table->unsignedBigInteger('est_impression')->nullable();
            $table->unsignedSmallInteger('avg_multiplier')->default(1);
            $table->unsignedBigInteger('est_impression_day')->nullable(); // computed
            $table->unsignedBigInteger('est_ad_spot')->nullable();         // computed

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_line_items');

        Schema::table('briefs', function (Blueprint $table) {
            $table->dropColumn('currency');
            $table->decimal('cpm', 18, 2)->nullable();
            $table->integer('duration_days')->nullable();
        });
    }
};
