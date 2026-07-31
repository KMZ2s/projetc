<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('order_number')->unique();
            $table->string('status')->default('pending'); // pending, processing, paid, shipped, delivered, cancelled, refunded
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->string('fulfillment_status')->default('pending'); // pending, shipped, delivered
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('shipping_method')->nullable();
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->onDelete('set null');
            $table->foreignId('billing_address_id')->nullable()->constrained('addresses')->onDelete('set null');
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};