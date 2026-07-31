<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('stores', 'active_theme')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->string('active_theme')->default('default')->after('tax_rate');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('stores', 'active_theme')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('active_theme');
            });
        }
    }

};
