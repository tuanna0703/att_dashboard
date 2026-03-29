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
        Schema::create('report_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('report_type', ['overdue_summary', 'upcoming_payments']);
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->time('send_time')->default('08:00');
            $table->unsignedTinyInteger('send_day')->nullable()->comment('1-7 for weekly (Mon=1), 1-28 for monthly');
            $table->json('recipients')->comment('[{"type":"role","value":"ceo"},{"type":"user","value":5}]');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_subscriptions');
    }
};
