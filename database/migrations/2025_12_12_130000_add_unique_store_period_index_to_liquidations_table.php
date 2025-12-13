<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('liquidations')) {
            return;
        }

        Schema::table('liquidations', function (Blueprint $table) {
            $indexName = 'liquidations_store_year_month_unique';

            // Evitar fallas si el índice ya existe por ejecuciones previas.
            if ($this->indexExists($table->getTable(), $indexName)) {
                return;
            }

            $table->unique(
                ['store_id', 'period_year', 'period_month'],
                $indexName
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('liquidations')) {
            return;
        }

        Schema::table('liquidations', function (Blueprint $table) {
            $indexName = 'liquidations_store_year_month_unique';

            if ($this->indexExists($table->getTable(), $indexName)) {
                $table->dropUnique($indexName);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();

            if (method_exists($connection, 'getDoctrineSchemaManager')) {
                $schemaManager = $connection->getDoctrineSchemaManager();

                if (method_exists($schemaManager, 'introspectTable')) {
                    $tableDetails = $schemaManager->introspectTable($table);

                    return $tableDetails->hasIndex($index);
                }

                if (method_exists($schemaManager, 'listTableIndexes')) {
                    $indexes = $schemaManager->listTableIndexes($table);

                    return array_key_exists($index, $indexes);
                }
            }
        } catch (\Throwable $e) {
            // Si Doctrine DBAL no esta disponible o falla la introspeccion,
            // asumimos que el اًndice no existe y dejamos que la migracion continue.
        }

        return false;
    }
};
