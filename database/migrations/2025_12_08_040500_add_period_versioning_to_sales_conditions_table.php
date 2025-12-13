<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_conditions', 'period_year')) {
                $table->integer('period_year')->nullable()->after('period_date');
            }

            if (!Schema::hasColumn('sales_conditions', 'period_month')) {
                $table->integer('period_month')->nullable()->after('period_year');
            }
        });

        // Backfill period fields from period_date when missing.
        DB::table('sales_conditions')
            ->select('id', 'period_date')
            ->whereNotNull('period_date')
            ->where(function ($query) {
                $query->whereNull('period_year')
                    ->orWhereNull('period_month');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $period = Carbon::parse($row->period_date);
                        DB::table('sales_conditions')
                            ->where('id', $row->id)
                            ->update([
                                'period_year' => (int) $period->format('Y'),
                                'period_month' => (int) $period->format('m'),
                            ]);
                    } catch (\Throwable $e) {
                        // Si no se puede parsear la fecha, dejamos los valores nulos para revision manual.
                    }
                }
            });

        Schema::table('sales_conditions', function (Blueprint $table) {
            if ($this->indexExists('sales_conditions', 'sales_conditions_simcard_id_unique')) {
                $table->dropUnique('sales_conditions_simcard_id_unique');
            }

            if (! $this->indexExists('sales_conditions', 'sales_conditions_simcard_id_period_year_period_month_unique')) {
                $table->unique(
                    ['simcard_id', 'period_year', 'period_month'],
                    'sales_conditions_simcard_id_period_year_period_month_unique'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table) {
            if ($this->indexExists('sales_conditions', 'sales_conditions_simcard_id_period_year_period_month_unique')) {
                $table->dropUnique('sales_conditions_simcard_id_period_year_period_month_unique');
            }

            if (! $this->indexExists('sales_conditions', 'sales_conditions_simcard_id_unique')) {
                $table->unique('simcard_id', 'sales_conditions_simcard_id_unique');
            }
        });

        Schema::table('sales_conditions', function (Blueprint $table) {
            if (Schema::hasColumn('sales_conditions', 'period_month')) {
                $table->dropColumn('period_month');
            }

            if (Schema::hasColumn('sales_conditions', 'period_year')) {
                $table->dropColumn('period_year');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();

            if (method_exists($connection, 'getDoctrineSchemaManager')) {
                $schemaManager = $connection->getDoctrineSchemaManager();

                if (method_exists($schemaManager, 'introspectTable')) {
                    $tableDetails = $schemaManager->introspectTable($table);
                    return $tableDetails->hasIndex($indexName);
                }

                if (method_exists($schemaManager, 'listTableIndexes')) {
                    $indexes = $schemaManager->listTableIndexes($table);
                    return array_key_exists($indexName, $indexes);
                }
            }
        } catch (\Throwable $e) {
            // Si Doctrine DBAL no esta disponible o falla la introspeccion, asumimos que el indice no existe.
        }

        return false;
    }
};
