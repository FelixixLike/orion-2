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
        Schema::create('operator_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simcard_id')->nullable()->constrained('simcards')->nullOnDelete();
            $table->string('phone_number')->nullable()->index();
            $table->string('city_code')->nullable()->comment('Código de ciudad del operador (ej: D1238.00010)');
            $table->string('coid')->nullable()->comment('Identificador del registro del operador');
            $table->string('commission_status')->nullable();
            $table->date('activation_date')->nullable();
            $table->date('cutoff_date')->nullable();
            $table->decimal('commission_paid_80', 15, 2)->nullable();
            $table->decimal('commission_paid_20', 15, 2)->nullable();
            $table->decimal('recharge_amount', 15, 2)->nullable();
            $table->string('recharge_period', 1)->nullable()->comment('Número del período de recarga');
            $table->string('custcode')->nullable()->comment('Código de cliente');
            $table->decimal('total_recharge_per_period', 15, 2)->nullable();
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->timestamps();

            $table->index('simcard_id');
            $table->index('import_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_reports');
    }
};
