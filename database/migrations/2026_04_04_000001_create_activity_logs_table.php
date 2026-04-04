<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Categorisation
            $table->string('log_name', 50)->index();   // 'brief', 'plan'
            $table->string('event', 80)->index();       // 'brief.created', 'plan.submitted'...

            // Polymorphic subject (Brief or Plan)
            $table->morphs('subject');

            // Who triggered this
            $table->foreignId('causer_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('causer_name', 200)->nullable(); // snapshot tên tại thời điểm log

            // Human-readable description
            $table->string('description');

            // Extra context: {brief_no, plan_no, version, comment, ...}
            $table->json('properties')->nullable();

            // Logs are immutable — only created_at needed
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
