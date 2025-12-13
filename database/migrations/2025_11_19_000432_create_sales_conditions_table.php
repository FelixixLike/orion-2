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
        Schema::create('sales_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simcard_id')->nullable()->constrained('simcards')->nullOnDelete();
            $table->string('iccid')->nullable()->index()->comment('ICCID limpio');
            $table->string('phone_number')->nullable()->index();
            $table->string('idpos', 6)->nullable()->comment('ID del punto de venta (6 dígitos)');
            $table->decimal('sale_price', 15, 2)->nullable()->comment('Precio al que se vendió la sim');
            $table->integer('commission_percentage')->nullable()->comment('Porcentaje de comisión (1, 2, 3, 4...)');
            $table->date('period_date')->nullable()->comment('Fecha/mes de la venta');
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
        Schema::dropIfExists('sales_conditions');
    }
};
