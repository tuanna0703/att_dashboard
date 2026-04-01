<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained('briefs')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->string('customer_file_path', 500)->nullable();  // file brief từ khách
            $table->string('planning_file_path', 500)->nullable();  // file planning từ adops
            $table->text('customer_note')->nullable();              // yêu cầu điều chỉnh của khách
            $table->text('adops_note')->nullable();                 // ghi chú adops khi gửi planning
            $table->enum('status', [
                'draft',
                'sent_to_customer',
                'customer_feedback',
                'approved',
                'rejected',
                'superseded',                                       // bị thay bởi revision mới hơn
            ])->default('draft');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_final')->default(false);            // revision được chọn cho booking
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_revisions');
    }
};
