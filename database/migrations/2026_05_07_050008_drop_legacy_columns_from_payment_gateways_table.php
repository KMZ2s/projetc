<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn(['sandbox_mode', 'api_secret']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->boolean('sandbox_mode')->default(true);
            $table->string('api_secret')->nullable();
        });
    }
};