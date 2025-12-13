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
        Schema::table('operator_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('operator_reports', 'period_year')) {
                $table->integer('period_year')->nullable()->after('cutoff_date');
            }
            if (! Schema::hasColumn('operator_reports', 'period_month')) {
                $table->integer('period_month')->nullable()->after('period_year');
            }
            if (! Schema::hasColumn('operator_reports', 'is_consolidated')) {
                $table->boolean('is_consolidated')->default(false)->after('import_id');
            }
            if (! Schema::hasColumn('operator_reports', 'cutoff_numbers')) {
                $table->json('cutoff_numbers')->nullable()->after('is_consolidated');
            }
        });

        // Backfill periodo desde cutoff_date (solo si existe).
        DB::table('operator_reports')
            ->whereNull('period_year')
            ->whereNull('period_month')
            ->whereNotNull('cutoff_date')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $date = Carbon::parse($row->cutoff_date);
                        DB::table('operator_reports')
                            ->where('id', $row->id)
                            ->update([
                                'period_year' => (int) $date->format('Y'),
                                'period_month' => (int) $date->format('m'),
                            ]);
                    } catch (\Throwable $e) {
                        // Si no se puede parsear, se deja para correccion manual.
                    }
                }
            });

        Schema::table('operator_reports', function (Blueprint $table) {
            if (! $this->indexExists('operator_reports', 'operator_reports_period_simcard_consolidated_unique')) {
                $table->unique(
                    ['simcard_id', 'period_year', 'period_month', 'is_consolidated', 'coid'],
                    'operator_reports_period_simcard_consolidated_unique'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            if ($this->indexExists('operator_reports', 'operator_reports_period_simcard_consolidated_unique')) {
                $table->dropUnique('operator_reports_period_simcard_consolidated_unique');
            }
        });

        Schema::table('operator_reports', function (Blueprint $table) {
            if (Schema::hasColumn('operator_reports', 'cutoff_numbers')) {
                $table->dropColumn('cutoff_numbers');
            }
            if (Schema::hasColumn('operator_reports', 'is_consolidated')) {
                $table->dropColumn('is_consolidated');
            }
            if (Schema::hasColumn('operator_reports', 'period_month')) {
                $table->dropColumn('period_month');
            }
            if (Schema::hasColumn('operator_reports', 'period_year')) {
                $table->dropColumn('period_year');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = method_exists($connection, 'getDoctrineSchemaManager')
                ? $connection->getDoctrineSchemaManager()
                : null;

            if (! $schemaManager) {
                return false;
            }

            if (method_exists($schemaManager, 'introspectTable')) {
                return $schemaManager->introspectTable($table)->hasIndex($indexName);
            }

            $indexes = $schemaManager->listTableIndexes($table);
            return array_key_exists($indexName, $indexes);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
