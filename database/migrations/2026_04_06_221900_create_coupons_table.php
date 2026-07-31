<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type'); // percentage, fixed
            $table->decimal('value', 10, 2);
            $table->decimal('min_order_value', 10, 2)->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_per_customer')->nullable();
            $table->integer('used_count')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('status')->default('active'); // active, inactive, expired
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};