<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('carrier', 20)->nullable()->after('order_id');
            $table->string('service_type', 10)->nullable()->after('carrier');
            $table->index('carrier');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['carrier']);
            $table->dropColumn(['carrier', 'service_type']);
        });
    }
};
