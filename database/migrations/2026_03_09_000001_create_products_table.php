<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // ชื่อสินค้า (เก็บไว้ในระบบ ไม่แสดงใน Label)
            $table->string('sku')->unique();           // SKU หลัก
            $table->string('seller_sku')->nullable();  // Seller SKU
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('min_stock')->default(10); // แจ้งเตือนเมื่อต่ำกว่า
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
