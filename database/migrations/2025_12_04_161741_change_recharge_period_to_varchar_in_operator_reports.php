<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar el tipo de columna usando SQL directo
        DB::statement('ALTER TABLE operator_reports ALTER COLUMN recharge_period TYPE varchar(1) USING recharge_period::varchar');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE operator_reports ALTER COLUMN recharge_period TYPE integer USING recharge_period::integer');
    }
};
