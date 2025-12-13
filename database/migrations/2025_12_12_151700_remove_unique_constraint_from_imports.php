<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usar SQL directo para eliminar el constraint de forma segura
        // Primero verificamos si existe y luego lo eliminamos
        DB::statement('
            DO $$ 
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint 
                    WHERE conname = \'imports_type_period_cutoff_unique\'
                ) THEN
                    ALTER TABLE imports DROP CONSTRAINT imports_type_period_cutoff_unique;
                END IF;
            END $$;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            // Restaurar el constraint único si se revierte la migración
            $table->unique(['type', 'period', 'cutoff_number'], 'imports_type_period_cutoff_unique');
        });
    }
};
