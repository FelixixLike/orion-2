<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Elimina campos redundantes (iccid, phone_number) de las tablas de importación
     * ya que estos datos están disponibles a través de la relación con simcards.
     */
    public function up(): void
    {
        // Eliminar campos redundantes de sales_conditions
        Schema::table('sales_conditions', function (Blueprint $table) {
            $table->dropIndex(['iccid']);
            $table->dropIndex(['phone_number']);
            $table->dropColumn(['iccid', 'phone_number']);
        });

        // Eliminar campos redundantes de recharges
        Schema::table('recharges', function (Blueprint $table) {
            $table->dropIndex(['phone_number']);
            $table->dropColumn('phone_number');
        });

        // Eliminar campos redundantes de operator_reports
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->dropIndex(['phone_number']);
            $table->dropColumn('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar campos en sales_conditions
        Schema::table('sales_conditions', function (Blueprint $table) {
            $table->string('iccid')->nullable()->after('simcard_id');
            $table->string('phone_number')->nullable()->after('iccid');
            $table->index('iccid');
            $table->index('phone_number');
        });

        // Restaurar campos en recharges
        Schema::table('recharges', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('simcard_id');
            $table->index('phone_number');
        });

        // Restaurar campos en operator_reports
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('simcard_id');
            $table->index('phone_number');
        });
    }
};
