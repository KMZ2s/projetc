<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_event_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_integration_id')
                ->constrained('tracking_integrations')
                ->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 64);
            $table->string('event_id');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tracking_integration_id', 'event_id'], 'tracking_delivery_event_unique');
            $table->index(['order_id', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_event_deliveries');
    }
};
