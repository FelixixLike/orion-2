<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar tabla stores - solo cambiar el tipo de dato, la restricción unique ya existe
        Schema::table('stores', function (Blueprint $table) {
            $table->string('idpos', 20)->change()->comment('Número único de hasta 20 dígitos');
        });

        // Actualizar tabla sales_conditions
        Schema::table('sales_conditions', function (Blueprint $table) {
            $table->string('idpos', 20)->nullable()->change()->comment('ID del punto de venta (hasta 20 dígitos)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir tabla stores - solo cambiar el tipo de dato, la restricción unique ya existe
        Schema::table('stores', function (Blueprint $table) {
            $table->string('idpos', 6)->change()->comment('Número único de 6 dígitos');
        });

        // Revertir tabla sales_conditions
        Schema::table('sales_conditions', function (Blueprint $table) {
            $table->string('idpos', 6)->nullable()->change()->comment('ID del punto de venta (6 dígitos)');
        });
    }
};
