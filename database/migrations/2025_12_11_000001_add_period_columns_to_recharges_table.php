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
        Schema::table('recharges', function (Blueprint $table) {
            if (! Schema::hasColumn('recharges', 'period_year')) {
                $table->unsignedSmallInteger('period_year')->nullable()->after('period_date');
            }

            if (! Schema::hasColumn('recharges', 'period_month')) {
                $table->unsignedTinyInteger('period_month')->nullable()->after('period_year');
            }

            if (! Schema::hasColumn('recharges', 'period_label')) {
                $table->string('period_label', 7)->nullable()->after('period_month');
            }

            $table->index(['period_year', 'period_month'], 'recharges_period_year_month_index');
            $table->index('period_label', 'recharges_period_label_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recharges', function (Blueprint $table) {
            if (Schema::hasColumn('recharges', 'period_label')) {
                $table->dropIndex('recharges_period_label_index');
                $table->dropColumn('period_label');
            }

            if (Schema::hasColumn('recharges', 'period_year') && Schema::hasColumn('recharges', 'period_month')) {
                $table->dropIndex('recharges_period_year_month_index');
            }

            if (Schema::hasColumn('recharges', 'period_month')) {
                $table->dropColumn('period_month');
            }

            if (Schema::hasColumn('recharges', 'period_year')) {
                $table->dropColumn('period_year');
            }
        });
    }
};
