<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usamos SQL raw para Postgres para poder hacer el cast si hay datos
        // O simplemente cambiar el tipo. Como los datos actuales son fechas incorrectas (1970-01-01),
        // podemos reiniciar a null o intentar convertir.
        // Dado que es un cambio de DATE a INTEGER, Postgres requiere USING.
        
        // Primero intentamos la forma standard de Laravel
        Schema::table('operator_reports', function (Blueprint $table) {
             // $table->integer('recharge_period')->nullable()->change();
        });

        // Pero para ser más seguros con Postgres y el cambio de tipo drástico:
        DB::statement('ALTER TABLE operator_reports ALTER COLUMN recharge_period TYPE integer USING NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE operator_reports ALTER COLUMN recharge_period TYPE date USING NULL');
    }
};
