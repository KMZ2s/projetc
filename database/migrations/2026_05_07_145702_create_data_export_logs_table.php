<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_export_logs', function (Blueprint $table) {
            $table->id();

            // Dataset e format como string ao invés de enum: o conjunto
            // de datasets evolui no DataExportService e não vale prender
            // a tabela ao schema atual. As constantes vivem no service.
            $table->string('dataset', 50);
            $table->string('format', 10);

            // Filtros usados (date_from, date_to, user_email, etc).
            // JSON pra acomodar variações futuras sem migration.
            $table->json('filters')->nullable();

            // Quem exportou. Nullable porque um cron ou comando artisan
            // que rode no futuro pode não ter user.
            $table->foreignId('exported_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('exported_at');

            $table->timestamps();

            $table->index('exported_at');
            $table->index(['dataset', 'exported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_logs');
    }
};