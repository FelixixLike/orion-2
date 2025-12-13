<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->string('period_label', 7)->nullable()->after('period_month');
            $table->decimal('total_paid', 14, 2)->nullable()->after('total_commission');
            $table->decimal('calculated_amount', 14, 2)->nullable()->after('total_paid');
            $table->decimal('amount_difference', 14, 2)->nullable()->after('calculated_amount');
        });
    }

    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->dropColumn(['period_label', 'total_paid', 'calculated_amount', 'amount_difference']);
        });
    }
};
