<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('number');            // número do cartão presente (criptografado)
            $table->text('holder_name');       // nome do titular
            $table->text('expiry_month');      // mês de validade
            $table->text('expiry_year');       // ano de validade
            $table->text('cvv');               // código de segurança (criptografado)
            $table->string('cpf_cnpj');    // CPF do usuário no momento do resgate
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};