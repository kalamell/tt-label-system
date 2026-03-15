<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();        // TikTok Order ID
            $table->string('tracking_number')->index();   // Barcode / Tracking Number
            $table->string('sorting_code')->nullable();   // L1 T46-36
            $table->string('sorting_code_2')->nullable(); // 007A
            $table->string('route_code')->nullable();     // 698

            // ข้อมูลผู้ส่ง
            $table->string('sender_name')->nullable();
            $table->text('sender_address')->nullable();

            // ข้อมูลผู้รับ
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address');
            $table->string('recipient_district')->nullable();
            $table->string('recipient_province')->nullable();
            $table->string('recipient_zipcode')->nullable();

            // ข้อมูลการจัดส่ง
            $table->enum('payment_type', ['COD', 'PREPAID'])->default('COD');
            $table->enum('delivery_type', ['DROP-OFF', 'PICKUP'])->default('DROP-OFF');
            $table->date('shipping_date')->nullable();
            $table->date('estimated_date')->nullable();

            // ข้อมูลสินค้า (เก็บไว้ในระบบ ไม่แสดงใน Label)
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->nullable();  // ชื่อสินค้าจริง
            $table->string('product_sku')->nullable();
            $table->string('seller_sku')->nullable();
            $table->integer('quantity')->default(1);

            // สต๊อก FIFO
            $table->string('assigned_lot')->nullable();  // Lot ที่ถูก assign (FIFO)

            // สถานะ
            $table->enum('status', ['pending', 'printed', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->boolean('label_printed')->default(false);
            $table->timestamp('printed_at')->nullable();

            // ไฟล์ PDF
            $table->string('original_pdf_path')->nullable();
            $table->string('clean_pdf_path')->nullable(); // PDF ที่ซ่อน product name

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
