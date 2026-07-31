<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_email')->nullable()->index()->after('customer_name');
            $table->string('customer_phone', 30)->nullable()->after('customer_email');
            $table->string('customer_document', 20)->nullable()->after('customer_phone');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('customer_devices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        DB::table('checkout_settings')->update([
            'credit_card_enabled' => false,
            'pix_enabled' => true,
            'pix_discount_percent' => 0,
            'pix_expires_minutes' => 10,
            'urgency_timer_enabled' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_email']);
            $table->dropColumn([
                'customer_name',
                'customer_email',
                'customer_phone',
                'customer_document',
            ]);
        });
    }
};
