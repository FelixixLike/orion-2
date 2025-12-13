<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            $table->integer('monthly_store_limit')->nullable()->comment('Límite mensual por tienda (ej: 1000 simcards)');
            $table->decimal('max_value', 10, 2)->nullable()->comment('Valor máximo para recargas');
        });
    }

    public function down(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            $table->dropColumn(['monthly_store_limit', 'max_value']);
        });
    }
};
