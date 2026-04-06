<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // buying_model on briefs (cascades to plan/booking)
        Schema::table('briefs', function (Blueprint $table) {
            $table->string('buying_model', 10)->default('io')->after('currency'); // io | cpm
        });

        // platform + placement on all line item tables
        foreach (['brief_line_items', 'plan_line_items', 'booking_line_items'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->string('platform')->nullable()->after('format');
                $table->string('placement')->nullable()->after('platform');
            });
        }
    }

    public function down(): void
    {
        Schema::table('briefs', fn (Blueprint $t) => $t->dropColumn('buying_model'));

        foreach (['brief_line_items', 'plan_line_items', 'booking_line_items'] as $tbl) {
            Schema::table($tbl, fn (Blueprint $t) => $t->dropColumn(['platform', 'placement']));
        }
    }
};
