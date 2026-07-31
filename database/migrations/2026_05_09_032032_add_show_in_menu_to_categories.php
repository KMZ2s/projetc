<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 11 — Reconciliação OldMoney.
 *
 * Adiciona flag `show_in_menu` em categories. Permite que o operador
 * esconda uma categoria do menu principal SEM precisar desativá-la
 * inteira (status=inactive a tira da listagem; show_in_menu=false só
 * a tira da navegação do header).
 *
 * Default true: comportamento existente preservado pra todas as
 * categorias já cadastradas após o `migrate`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_in_menu')
                ->default(true)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('show_in_menu');
        });
    }
};