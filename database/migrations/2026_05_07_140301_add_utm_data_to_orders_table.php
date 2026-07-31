<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // JSON com as 5 chaves canônicas (utm_source, utm_medium,
            // utm_campaign, utm_content, utm_term) capturadas pela
            // session via CaptureUtmParameters middleware.
            //
            // Nullable porque pedidos antigos (pré-Fase 8) e pedidos
            // sem UTM na sessão simplesmente não têm dado pra gravar.
            //
            // Sem índice por padrão: agregação de marketing roda em
            // batch via DataExportService, não em hot path.
            $table->json('utm_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('utm_data');
        });
    }
};