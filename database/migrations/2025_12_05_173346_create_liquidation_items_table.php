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
        Schema::create('liquidation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidation_id')->constrained('liquidations')->cascadeOnDelete();
            $table->foreignId('simcard_id')->constrained('simcards')->cascadeOnDelete();
            $table->foreignId('operator_report_id')->nullable()->constrained('operator_reports')->nullOnDelete();
            $table->foreignId('sales_condition_id')->nullable()->constrained('sales_conditions')->nullOnDelete();
            $table->decimal('total_commission', 15, 2)->comment('Suma de commission_paid_80 + commission_paid_20');
            $table->decimal('recharge_discount', 15, 2)->default(0)->comment('Valor recarga * payment_percentage / 100');
            $table->decimal('commission_after_discount', 15, 2)->comment('total_commission - recharge_discount');
            $table->decimal('liquidation_multiplier', 10, 6)->comment('commission_percentage / payment_percentage de sales_condition');
            $table->decimal('final_amount', 15, 2)->comment('commission_after_discount * liquidation_multiplier');
            $table->date('period_date')->nullable()->comment('Fecha del período');
            $table->timestamps();

            $table->index('liquidation_id');
            $table->index('simcard_id');
            $table->index('operator_report_id');
            $table->index('sales_condition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquidation_items');
    }
};
