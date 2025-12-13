<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Forzar eliminación del constraint usando SQL directo
        try {
            DB::statement('ALTER TABLE imports DROP CONSTRAINT IF EXISTS imports_type_period_cutoff_unique CASCADE');
            echo "✓ Constraint eliminado exitosamente\n";
        } catch (\Exception $e) {
            echo "Error al eliminar constraint: " . $e->getMessage() . "\n";
        }

        // También intentar eliminar cualquier índice único relacionado
        try {
            DB::statement('DROP INDEX IF EXISTS imports_type_period_cutoff_unique CASCADE');
            echo "✓ Índice eliminado exitosamente\n";
        } catch (\Exception $e) {
            echo "Info: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir - queremos mantener la flexibilidad de múltiples importaciones
    }
};
