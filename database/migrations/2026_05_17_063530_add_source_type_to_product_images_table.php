<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('source_type', 30)
                ->default('upload')
                ->after('src')
                ->index();
        });

        DB::table('product_images')
            ->where('src', 'like', 'http://%')
            ->orWhere('src', 'like', 'https://%')
            ->update(['source_type' => 'external_url']);
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};