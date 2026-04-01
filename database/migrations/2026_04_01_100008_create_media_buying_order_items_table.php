<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_buying_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_buying_order_id')->constrained('media_buying_orders')->cascadeOnDelete();
            $table->foreignId('ad_network_id')->constrained('ad_networks')->restrictOnDelete();
            $table->string('description', 300)->nullable();
            $table->integer('screen_count')->default(1);
            $table->integer('days')->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_price', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_buying_order_items');
    }
};
