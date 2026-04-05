<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_buying_order_items', function (Blueprint $table) {
            $table->json('targeting')->nullable()->after('ad_network_id');
            $table->json('targeting_names')->nullable()->after('targeting');
        });
    }

    public function down(): void
    {
        Schema::table('media_buying_order_items', function (Blueprint $table) {
            $table->dropColumn(['targeting', 'targeting_names']);
        });
    }
};
