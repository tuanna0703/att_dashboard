<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_ad_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained('briefs')->cascadeOnDelete();
            $table->foreignId('ad_network_id')->constrained('ad_networks')->restrictOnDelete();
            $table->integer('screen_count')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['brief_id', 'ad_network_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_ad_networks');
    }
};
