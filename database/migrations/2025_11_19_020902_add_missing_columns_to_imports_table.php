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
        Schema::table('imports', function (Blueprint $table) {
            // Verificar y agregar solo las columnas que no existen
            if (!Schema::hasColumn('imports', 'batch_id')) {
                $table->uuid('batch_id')->nullable()->index()->after('id')->comment('ID de tanda/lote para agrupar múltiples importaciones');
            }
            
            // Las demás columnas ya existen según las migraciones anteriores
            // Solo verificar/modificar si es necesario
            
            // Asegurar que type sea nullable
            if (Schema::hasColumn('imports', 'type')) {
                // Ya se hizo nullable en la migración anterior
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'batch_id',
                'file',
                'type',
                'description',
                'status',
                'total_rows',
                'processed_rows',
                'failed_rows',
                'errors',
                'created_by',
            ]);
        });
    }
};
