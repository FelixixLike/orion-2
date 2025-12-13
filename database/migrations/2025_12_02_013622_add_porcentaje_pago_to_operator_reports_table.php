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
            // Using unsignedTinyInteger to store percentages like 8, 10, 15, 100
            // This allows values from 0 to 255 (more than enough for percentages)
            $table->unsignedTinyInteger('payment_percentage')->nullable()->after('recharge_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->dropColumn('payment_percentage');
        });
    }
};
