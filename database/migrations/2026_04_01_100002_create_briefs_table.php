<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefs', function (Blueprint $table) {
            $table->id();
            $table->string('brief_no', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sale_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('adops_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('campaign_name', 200);
            $table->decimal('budget', 18, 2)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('screen_count')->nullable();
            $table->decimal('cpm', 18, 2)->nullable();
            $table->integer('duration_days')->nullable();
            $table->enum('status', [
                'draft',
                'sent_to_adops',
                'planning_ready',
                'sent_to_customer',
                'customer_feedback',
                'confirmed',
                'rejected',
                'converted',
            ])->default('draft');
            // current_revision_id được thêm ở migration 100005 sau khi brief_revisions tồn tại
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefs');
    }
};
