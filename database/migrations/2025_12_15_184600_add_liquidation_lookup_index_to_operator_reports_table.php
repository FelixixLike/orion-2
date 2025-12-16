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
            // Índice compuesto para optimizar la consulta principal de liquidación
            $table->index(['is_consolidated', 'liquidation_item_id', 'period_year', 'period_month'], 'idx_op_reports_liquidation_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->dropIndex('idx_op_reports_liquidation_lookup');
        });
    }
};
