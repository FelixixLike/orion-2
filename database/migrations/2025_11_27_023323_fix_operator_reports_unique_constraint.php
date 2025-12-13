<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar índice único anterior si existe (usando SQL directo para IF EXISTS)
        DB::statement('ALTER TABLE operator_reports DROP CONSTRAINT IF EXISTS operator_reports_coid_unique');
        
        Schema::table('operator_reports', function (Blueprint $table) {
            // Agregar índice único compuesto
            $table->unique(['coid', 'recharge_period', 'phone_number'], 'operator_reports_composite_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->dropUnique('operator_reports_composite_unique');
        });
        
        // Restaurar índice anterior
        DB::statement('ALTER TABLE operator_reports ADD CONSTRAINT operator_reports_coid_unique UNIQUE (coid)');
    }
};
