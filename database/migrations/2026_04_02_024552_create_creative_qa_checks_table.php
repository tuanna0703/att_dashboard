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
        Schema::create('creative_qa_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_asset_id')
                  ->constrained('creative_assets')->cascadeOnDelete();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();

            $table->enum('check_type', [
                'resolution', 'duration', 'file_size', 'format',
                'codec', 'bitrate', 'fps', 'content', 'audio', 'aspect_ratio',
            ]);
            $table->string('expected_value')->nullable();
            $table->string('actual_value')->nullable();
            $table->enum('result', ['pass', 'fail', 'warning', 'skipped']);
            $table->boolean('is_blocking')->default(true);
            $table->text('note')->nullable();
            $table->timestamp('checked_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_qa_checks');
    }
};
