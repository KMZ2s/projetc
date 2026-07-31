<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Toggle do modo de manutenção. Quando true, visitantes anônimos
            // recebem 503; admins logados continuam vendo a loja normal.
            $table->boolean('maintenance_mode')->default(false)->after('active_theme');

            // Mensagem customizável exibida na página de manutenção.
            // Null = usa fallback default da view.
            $table->text('maintenance_message')->nullable()->after('maintenance_mode');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['maintenance_mode', 'maintenance_message']);
        });
    }
};