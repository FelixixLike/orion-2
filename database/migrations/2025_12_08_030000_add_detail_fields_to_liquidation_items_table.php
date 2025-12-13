<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidation_items', function (Blueprint $table) {
            if (!Schema::hasColumn('liquidation_items', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('simcard_id');
            }
            if (!Schema::hasColumn('liquidation_items', 'iccid')) {
                $table->string('iccid')->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('liquidation_items', 'commission_status')) {
                $table->string('commission_status')->nullable()->after('iccid');
            }
            if (!Schema::hasColumn('liquidation_items', 'activation_date')) {
                $table->date('activation_date')->nullable()->after('commission_status');
            }
            if (!Schema::hasColumn('liquidation_items', 'cutoff_date')) {
                $table->date('cutoff_date')->nullable()->after('activation_date');
            }
            if (!Schema::hasColumn('liquidation_items', 'custcode')) {
                $table->string('custcode')->nullable()->after('cutoff_date');
            }
            if (!Schema::hasColumn('liquidation_items', 'operator_total_recharge')) {
                $table->decimal('operator_total_recharge', 15, 2)->nullable()->after('custcode');
            }
            if (!Schema::hasColumn('liquidation_items', 'movilco_recharge_amount')) {
                $table->decimal('movilco_recharge_amount', 15, 2)->nullable()->after('operator_total_recharge');
            }
            if (!Schema::hasColumn('liquidation_items', 'discount_total_period')) {
                $table->decimal('discount_total_period', 15, 2)->nullable()->after('movilco_recharge_amount');
            }
            if (!Schema::hasColumn('liquidation_items', 'discount_residual')) {
                $table->decimal('discount_residual', 15, 2)->nullable()->after('discount_total_period');
            }
            if (!Schema::hasColumn('liquidation_items', 'base_liquidation_final')) {
                $table->decimal('base_liquidation_final', 15, 2)->nullable()->after('discount_residual');
            }
            if (!Schema::hasColumn('liquidation_items', 'period')) {
                $table->string('period', 7)->nullable()->after('base_liquidation_final');
            }
            if (!Schema::hasColumn('liquidation_items', 'liquidation_month')) {
                $table->string('liquidation_month', 7)->nullable()->after('period');
            }
            if (!Schema::hasColumn('liquidation_items', 'sim_value')) {
                $table->decimal('sim_value', 15, 2)->nullable()->after('liquidation_month');
            }
            if (!Schema::hasColumn('liquidation_items', 'residual_percentage')) {
                $table->decimal('residual_percentage', 8, 3)->nullable()->after('sim_value');
            }
            if (!Schema::hasColumn('liquidation_items', 'transfer_percentage')) {
                $table->decimal('transfer_percentage', 8, 3)->nullable()->after('residual_percentage');
            }
            if (!Schema::hasColumn('liquidation_items', 'residual_payment')) {
                $table->decimal('residual_payment', 15, 2)->nullable()->after('transfer_percentage');
            }
            if (!Schema::hasColumn('liquidation_items', 'idpos')) {
                $table->string('idpos')->nullable()->after('residual_payment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('liquidation_items', function (Blueprint $table) {
            $table->dropColumn([
                'phone_number',
                'iccid',
                'commission_status',
                'activation_date',
                'cutoff_date',
                'custcode',
                'operator_total_recharge',
                'movilco_recharge_amount',
                'discount_total_period',
                'discount_residual',
                'base_liquidation_final',
                'period',
                'liquidation_month',
                'sim_value',
                'residual_percentage',
                'transfer_percentage',
                'residual_payment',
                'idpos',
            ]);
        });
    }
};
