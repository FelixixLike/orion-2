<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Listar y eliminar TODOS los constraints únicos en la tabla imports
        // que involucren las columnas type, period, cutoff_number
        $constraints = DB::select("
            SELECT conname 
            FROM pg_constraint 
            WHERE conrelid = 'imports'::regclass 
            AND contype = 'u'
        ");

        foreach ($constraints as $constraint) {
            $constraintName = $constraint->conname;

            // Solo eliminar si contiene las palabras clave relacionadas
            if (
                str_contains($constraintName, 'type') ||
                str_contains($constraintName, 'period') ||
                str_contains($constraintName, 'cutoff')
            ) {

                DB::statement("ALTER TABLE imports DROP CONSTRAINT IF EXISTS {$constraintName}");

                echo "Eliminado constraint: {$constraintName}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No podemos restaurar automáticamente porque no sabemos cuál era el constraint original
        // Si necesitas revertir, deberás crear manualmente el constraint
    }
};
