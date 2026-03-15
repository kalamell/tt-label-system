<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->change();
            $table->text('recipient_address')->nullable()->change();
            $table->string('recipient_district')->nullable()->change();
            $table->string('recipient_province')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('recipient_name')->nullable(false)->change();
            $table->text('recipient_address')->nullable(false)->change();
            $table->string('recipient_district')->nullable(false)->change();
            $table->string('recipient_province')->nullable(false)->change();
        });
    }
};
