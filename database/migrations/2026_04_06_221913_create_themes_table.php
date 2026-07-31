<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('directory')->unique();
            $table->string('version')->nullable();
            $table->string('author')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('settings_data')->nullable(); // salva as configurações do tema
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};