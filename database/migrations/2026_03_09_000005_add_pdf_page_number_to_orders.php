<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // หน้าที่ใน PDF ต้นฉบับ — ใช้สำหรับ overlay แบบแม่นยำ
            $table->unsignedSmallInteger('pdf_page_number')->nullable()->after('original_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pdf_page_number');
        });
    }
};
