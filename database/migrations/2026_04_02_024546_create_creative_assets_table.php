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
        Schema::create('creative_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_submission_id')
                  ->constrained('creative_submissions')->cascadeOnDelete();
            $table->foreignId('booking_line_item_id')
                  ->nullable()->constrained('booking_line_items')->nullOnDelete();

            // File metadata
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('storage_path');
            $table->string('storage_disk', 20)->default('public');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size_bytes');
            $table->string('checksum_md5', 32)->nullable();

            // Asset technical specs
            $table->enum('asset_type', ['video', 'image', 'html5', 'audio']);
            $table->integer('duration_seconds')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('resolution', 20)->nullable();         // "1920x1080"
            $table->decimal('aspect_ratio', 5, 2)->nullable();
            $table->string('codec', 30)->nullable();              // h264, h265
            $table->integer('bitrate_kbps')->nullable();
            $table->decimal('fps', 5, 2)->nullable();

            // QA result
            $table->enum('qa_status', ['pending', 'passed', 'failed', 'warning'])->default('pending');
            $table->foreignId('qa_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qa_reviewed_at')->nullable();
            $table->text('qa_notes')->nullable();

            // CMS upload tracking
            $table->boolean('uploaded_to_cms')->default(false);
            $table->string('cms_asset_id')->nullable();
            $table->timestamp('cms_uploaded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_assets');
    }
};
