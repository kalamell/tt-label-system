<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('lot_number');              // เช่น 03/100
            $table->integer('quantity_received');       // จำนวนที่รับเข้า
            $table->integer('quantity_remaining');      // จำนวนคงเหลือ
            $table->date('received_date');              // วันที่รับเข้า
            $table->date('expiry_date')->nullable();    // วันหมดอายุ
            $table->decimal('cost_per_unit', 10, 2)->default(0); // ต้นทุนต่อหน่วย
            $table->enum('status', ['active', 'depleted', 'expired'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'received_date']); // สำหรับ FIFO query
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
    }
};
