<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('background_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // 'import', 'delete_orphans', etc.
            $table->string('name'); // "Importando Recargas", "Limpiando..."
            $table->integer('total')->default(0);
            $table->integer('progress')->default(0);
            $table->string('status')->default('running'); // running, completed, failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('background_processes');
    }
};
