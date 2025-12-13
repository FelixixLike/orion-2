<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            // Cambiamos de tinyInteger (18) a decimal (0.1800)
            // Permitimos hasta 8 dígitos, 4 de ellos decimales.
            $table->decimal('payment_percentage', 8, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            // Revertir es peligroso si hay datos decimales, pero definimos el camino de vuelta
            $table->unsignedTinyInteger('payment_percentage')->nullable()->change();
        });
    }
};
