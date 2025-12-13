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
        Schema::table('operator_reports', function (Blueprint $table) {
            // Eliminar la restricción única estricta para permitir cargas múltiples aditivas
            // incluso si hay duplicados de COID/Simcard en el mismo periodo.
            $table->dropUnique('operator_reports_period_simcard_consolidated_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->unique(
                ['simcard_id', 'period_year', 'period_month', 'is_consolidated', 'coid'],
                'operator_reports_period_simcard_consolidated_unique'
            );
        });
    }
};
