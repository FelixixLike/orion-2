<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('liquidations')) {
            return;
        }

        Schema::table('liquidations', function (Blueprint $table) {
            $table->unique(
                ['store_id', 'period_year', 'period_month', 'version'],
                'liquidations_store_year_month_version_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('liquidations')) {
            return;
        }

        Schema::table('liquidations', function (Blueprint $table) {
            $table->dropUnique('liquidations_store_year_month_version_unique');
        });
    }
};
