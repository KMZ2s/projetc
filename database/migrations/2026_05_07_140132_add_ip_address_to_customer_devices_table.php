<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            // 45 chars cobre IPv4 (15) e IPv6 (39, ou 45 com zona "%eth0").
            // Nullable porque devices antigos não têm captura de IP.
            $table->string('ip_address', 45)->nullable();

            // Index pra suportar buscas/filtros por IP no futuro
            // (fraude, geo-analytics, agrupamento de devices por origem).
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('customer_devices', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropColumn('ip_address');
        });
    }
};