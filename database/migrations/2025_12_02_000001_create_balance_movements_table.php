<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('balance_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores');
            $table->dateTime('movement_date');
            $table->string('movement_type', 32);
            $table->string('source_type')->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'movement_date']);
            $table->index(['store_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_movements');
    }
};
