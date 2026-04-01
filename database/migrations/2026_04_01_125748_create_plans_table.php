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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_no', 50)->unique();
            $table->foreignId('brief_id')->constrained('briefs')->restrictOnDelete();
            $table->unsignedSmallInteger('version')->default(1);

            // Mirror Brief fields — AdOps có thể điều chỉnh so với brief gốc
            $table->string('campaign_name', 200);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 18, 2)->nullable();
            $table->decimal('cpm', 18, 2)->nullable();
            $table->integer('screen_count')->nullable();
            $table->integer('duration_days')->nullable();

            // Planning details
            $table->text('note')->nullable();            // AdOps ghi chú kế hoạch
            $table->string('file_path', 500)->nullable(); // File planning đính kèm

            // Phản hồi từ Sale/người tạo brief
            $table->text('sale_comment')->nullable();    // Comment khi re-plan hoặc reject

            $table->enum('status', [
                'draft',        // AdOps đang soạn
                'submitted',    // Đã gửi cho Sale review
                'accepted',     // Sale chấp nhận → Brief confirmed
                're_plan',      // Sale yêu cầu điều chỉnh → AdOps tạo plan mới
                'rejected',     // Sale từ chối → Brief closed
            ])->default('draft');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
