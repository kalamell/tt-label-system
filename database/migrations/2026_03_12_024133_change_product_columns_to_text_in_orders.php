<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เปลี่ยน product_name, product_sku, seller_sku จาก varchar(255) → text
     * เนื่องจาก order ที่มีสินค้าหลายรายการ (continuation pages) อาจมีข้อมูลยาวเกิน 255 ตัว
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('product_name')->nullable()->change();
            $table->text('product_sku')->nullable()->change();
            $table->text('seller_sku')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('product_name')->nullable()->change();
            $table->string('product_sku')->nullable()->change();
            $table->string('seller_sku')->nullable()->change();
        });
    }
};
