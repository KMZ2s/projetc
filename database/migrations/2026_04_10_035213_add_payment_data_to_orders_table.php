<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // ID da transação no BlackcatPay (ex: TXN-123-ABC)
            $table->string('blackcat_transaction_id')->nullable()->after('transaction_id');
            // JSON com dados do pagamento: QR Code PIX, últimos dígitos do cartão, 3DS data, etc.
            $table->json('payment_data')->nullable()->after('blackcat_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['blackcat_transaction_id', 'payment_data']);
        });
    }
};