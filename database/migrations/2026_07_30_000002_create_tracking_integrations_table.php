<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 32)->index();
            $table->string('public_id')->nullable();
            $table->text('access_token')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('browser_enabled')->default(true);
            $table->boolean('server_enabled')->default(false);
            $table->json('events')->nullable();
            $table->string('scope_mode', 16)->default('all');
            $table->json('product_ids')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 32)->nullable();
            $table->timestamps();

            $table->index(['provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_integrations');
    }
};
