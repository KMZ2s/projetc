<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->string('browser_language')->nullable();
            $table->unsignedTinyInteger('color_depth')->nullable();
            $table->unsignedSmallInteger('screen_height')->nullable();
            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->smallInteger('time_difference')->nullable(); // timezone offset em minutos
            $table->boolean('java_enabled')->default(false);
            $table->boolean('javascript_enabled')->default(true);
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_devices');
    }
};