<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // briefs: remove fields no longer used in form
        // - start_date / end_date  → dates now live on brief_line_items
        // - screen_count           → never exposed in form
        // - objective              → added but never used
        // - target_audience        → added but never used
        Schema::table('briefs', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'screen_count', 'objective', 'target_audience']);
        });

        // brief_line_items: remove fields removed from form
        // - platform       → removed from line item form
        // - placement      → removed from line item form
        // - location       → removed from line item form
        // - avg_multiplier → removed from line item form
        Schema::table('brief_line_items', function (Blueprint $table) {
            $table->dropColumn(['platform', 'placement', 'location', 'avg_multiplier']);
        });
    }

    public function down(): void
    {
        Schema::table('briefs', function (Blueprint $table) {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('screen_count')->nullable();
            $table->text('objective')->nullable();
            $table->text('target_audience')->nullable();
        });

        Schema::table('brief_line_items', function (Blueprint $table) {
            $table->string('platform', 100)->nullable();
            $table->string('placement', 100)->nullable();
            $table->string('location', 100)->nullable();
            $table->unsignedSmallInteger('avg_multiplier')->default(1);
        });
    }
};
