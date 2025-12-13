<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            if (! Schema::hasColumn('routes', 'code')) {
                $table->string('code', 50)->unique()->after('id');
            }

            if (! Schema::hasColumn('routes', 'name')) {
                $table->string('name')->nullable()->after('code');
            }

            if (! Schema::hasColumn('routes', 'type')) {
                $table->string('type', 20)->default('route')->after('name');
            }

            if (! Schema::hasColumn('routes', 'description')) {
                $table->string('description')->nullable()->after('type');
            }

            if (! Schema::hasColumn('routes', 'origin')) {
                $table->string('origin')->nullable()->after('description');
            }

            if (! Schema::hasColumn('routes', 'destination')) {
                $table->string('destination')->nullable()->after('origin');
            }

            if (! Schema::hasColumn('routes', 'distance')) {
                $table->decimal('distance', 8, 2)->nullable()->after('destination');
            }

            if (! Schema::hasColumn('routes', 'estimated_time')) {
                $table->integer('estimated_time')->nullable()->after('distance');
            }

            if (! Schema::hasColumn('routes', 'active')) {
                $table->boolean('active')->default(true)->after('estimated_time');
            }

            if (! Schema::hasColumn('routes', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            if (Schema::hasColumn('routes', 'code')) {
                $table->dropUnique('routes_code_unique');
                $table->dropColumn('code');
            }

            foreach ([
                'name',
                'type',
                'description',
                'origin',
                'destination',
                'distance',
                'estimated_time',
                'active',
                'deleted_at',
            ] as $column) {
                if (Schema::hasColumn('routes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

