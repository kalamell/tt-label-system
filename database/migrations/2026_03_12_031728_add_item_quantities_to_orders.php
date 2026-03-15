<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เพิ่ม item_quantities สำหรับเก็บ qty ของแต่ละรายการสินค้า คั่นด้วย " | "
     * เช่น "1 | 2 | 1" สำหรับ order ที่มี 3 รายการ
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('item_quantities')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('item_quantities');
        });
    }
};
