<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('position', 30)->default('member'); // head, deputy_head, member
            $table->foreignId('role_id')->nullable()->constrained(
                table: 'roles', // Spatie roles table
            )->nullOnDelete();
            $table->boolean('is_primary')->default(false); // phòng ban chính của user
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'user_id', 'position'], 'dept_user_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_positions');
    }
};
