<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Adiciona os campos que estavam em 'customers'
            $table->string('first_name')->nullable()->after('name');      // nome próprio
            $table->string('last_name')->nullable()->after('first_name'); // sobrenome
            $table->string('phone')->nullable()->after('email');
            $table->string('cpf_cnpj')->nullable()->after('phone');
            $table->string('status')->default('active')->after('cpf_cnpj');
            $table->boolean('accepts_marketing')->default(false)->after('status');
            $table->text('notes')->nullable()->after('accepts_marketing');
            $table->timestamp('last_login')->nullable()->after('notes');

            // Campo para diferenciar administradores (segurança)
            $table->boolean('is_admin')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'cpf_cnpj',
                'status',
                'accepts_marketing',
                'notes',
                'last_login',
                'is_admin',
            ]);
        });
    }
};