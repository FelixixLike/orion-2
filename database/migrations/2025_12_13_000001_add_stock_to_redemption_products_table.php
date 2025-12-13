<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add stock column only if it does not exist
        Schema::table('redemption_products', function (Blueprint $table) {
            if (!Schema::hasColumn('redemption_products', 'stock')) {
                $table->integer('stock')
                    ->nullable()
                    ->comment('Cantidad disponible, null = stock ilimitado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            if (Schema::hasColumn('redemption_products', 'stock')) {
                $table->dropColumn('stock');
            }
        });
    }
};