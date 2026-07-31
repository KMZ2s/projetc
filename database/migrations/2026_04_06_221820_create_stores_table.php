<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 3)->default('BRL');
            $table->string('currency_symbol', 5)->default('R$');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('active_theme')->default('default');
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};