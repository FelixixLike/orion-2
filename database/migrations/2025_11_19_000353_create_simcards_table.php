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
        Schema::create('simcards', function (Blueprint $table) {
            $table->id();
            $table->string('iccid')->unique()->comment('ICCID limpio (sin primeros 2 y último dígito)');
            $table->string('phone_number')->nullable()->index()->comment('Número de teléfono');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simcards');
    }
};
