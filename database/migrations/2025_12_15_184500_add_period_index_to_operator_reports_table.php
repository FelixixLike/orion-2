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
            $table->index(['period_year', 'period_month', 'is_consolidated'], 'idx_op_reports_period_consol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->dropIndex('idx_op_reports_period_consol');
        });
    }
};
