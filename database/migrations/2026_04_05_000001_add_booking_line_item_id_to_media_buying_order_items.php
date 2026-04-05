<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_buying_order_items', function (Blueprint $table) {
            $table->foreignId('booking_line_item_id')
                  ->nullable()
                  ->after('media_buying_order_id')
                  ->constrained('booking_line_items')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_buying_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_line_item_id');
        });
    }
};
