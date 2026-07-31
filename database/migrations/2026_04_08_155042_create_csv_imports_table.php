<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type');                          // products, customers, stock
            $table->string('filename');
            $table->string('status')->default('pending');    // pending, processing, done, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->string('error_file')->nullable();        // caminho do CSV de erros
            $table->string('zip_file')->nullable();          // caminho do ZIP de imagens
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_imports');
    }
};