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
        Schema::create('recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simcard_id')->nullable()->constrained('simcards')->nullOnDelete();
            $table->string('phone_number')->nullable()->index();
            $table->decimal('recharge_amount', 15, 2)->nullable();
            $table->date('period_date')->nullable()->comment('Fecha/mes de la recarga');
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
        Schema::dropIfExists('recharges');
    }
};
