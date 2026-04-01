<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('briefs', function (Blueprint $table) {
            $table->foreignId('current_revision_id')
                ->nullable()
                ->after('status')
                ->constrained('brief_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('briefs', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
            $table->dropColumn('current_revision_id');
        });
    }
};
