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
        Schema::table('operator_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('operator_reports', 'total_commission')) {
                $table->decimal('total_commission', 15, 2)->nullable()->after('commission_paid_20');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            if (Schema::hasColumn('operator_reports', 'total_commission')) {
                $table->dropColumn('total_commission');
            }
        });
    }
};
