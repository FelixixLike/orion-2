<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Indices para tabla users
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->hasIndex('users', 'users_email_index')) {
                    $table->index('email')->comment('Para busquedas por email');
                }
                if (!$this->hasIndex('users', 'users_username_index')) {
                    $table->index('username')->comment('Para busquedas por username');
                }
                if (Schema::hasColumn('users', 'status') && !$this->hasIndex('users', 'users_status_index')) {
                    $table->index('status')->comment('Filtros por estado');
                }
            });
        }

        // Indices para tabla imports
        if (Schema::hasTable('imports')) {
            Schema::table('imports', function (Blueprint $table) {
                if (!$this->hasIndex('imports', 'imports_batch_id_index')) {
                    $table->index('batch_id')->comment('Agrupar por lote');
                }
                if (!$this->hasIndex('imports', 'imports_type_index')) {
                    $table->index('type')->comment('Filtrar por tipo');
                }
                if (Schema::hasColumn('imports', 'status') && !$this->hasIndex('imports', 'imports_status_index')) {
                    $table->index('status')->comment('Filtrar por estado');
                }
                if (!$this->hasIndex('imports', 'imports_created_at_index')) {
                    $table->index('created_at')->comment('Ordenar por fecha');
                }
            });
        }

        // Indices para tabla simcards
        if (Schema::hasTable('simcards')) {
            Schema::table('simcards', function (Blueprint $table) {
                if (Schema::hasColumn('simcards', 'status') && !$this->hasIndex('simcards', 'simcards_status_index')) {
                    $table->index('status')->comment('Filtrar por estado');
                }
                if (Schema::hasColumn('simcards', 'iccid') && !$this->hasIndex('simcards', 'simcards_iccid_index')) {
                    $table->index('iccid')->comment('Busqueda por ICCID');
                }
                if (Schema::hasColumn('simcards', 'operator_id') && !$this->hasIndex('simcards', 'simcards_operator_id_index')) {
                    $table->index('operator_id')->comment('Relacion con operador');
                }
            });
        }

        // Indices para tabla operator_reports
        if (Schema::hasTable('operator_reports')) {
            Schema::table('operator_reports', function (Blueprint $table) {
                if (Schema::hasColumn('operator_reports', 'operator_id') && !$this->hasIndex('operator_reports', 'operator_reports_operator_id_index')) {
                    $table->index('operator_id')->comment('Filtrar por operador');
                }
                if (Schema::hasColumn('operator_reports', 'simcard_id') && !$this->hasIndex('operator_reports', 'operator_reports_simcard_id_index')) {
                    $table->index('simcard_id')->comment('Relacion con SIM');
                }
                if (!$this->hasIndex('operator_reports', 'operator_reports_created_at_index')) {
                    $table->index('created_at')->comment('Ordenar por fecha');
                }
            });
        }

        // Indices para tabla stores
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                if (Schema::hasColumn('stores', 'status') && !$this->hasIndex('stores', 'stores_status_index')) {
                    $table->index('status')->comment('Filtrar por estado');
                }
                if (!$this->hasIndex('stores', 'stores_name_index')) {
                    $table->index('name')->comment('Busqueda por nombre');
                }
            });
        }
    }

    public function down(): void
    {
        // No se revierten los indices (son seguros de dejar)
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select(
                "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, $indexName]
            );

            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
