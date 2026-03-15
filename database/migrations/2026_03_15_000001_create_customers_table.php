<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->nullable()->index();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable()->index();
            $table->string('zipcode', 10)->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
