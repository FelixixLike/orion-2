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
        Schema::table('imports', function (Blueprint $table) {
            if (!Schema::hasColumn('imports', 'period')) {
                $table->string('period', 7)->nullable()->after('description')->comment('Periodo en formato YYYY-MM');
            }

            if (!Schema::hasColumn('imports', 'cutoff_number')) {
                $table->unsignedTinyInteger('cutoff_number')->default(0)->after('period')->comment('Número de corte (1-4) para reportes de operador, 0 para el resto');
            }

            if (!Schema::hasColumn('imports', 'ignored_duplicates')) {
                $table->unsignedInteger('ignored_duplicates')->default(0)->after('failed_rows');
            }

            $table->unique(['type', 'period', 'cutoff_number'], 'imports_type_period_cutoff_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropUnique('imports_type_period_cutoff_unique');
            $table->dropColumn(['period', 'cutoff_number', 'ignored_duplicates']);
        });
    }
};
