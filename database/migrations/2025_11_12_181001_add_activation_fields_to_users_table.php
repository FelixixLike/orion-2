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
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_token', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('activation_token_expires_at')->nullable()->after('activation_token');
            $table->boolean('must_change_password')->default(false)->after('activation_token_expires_at');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'activation_token',
                'activation_token_expires_at',
                'must_change_password',
                'password_changed_at',
            ]);
        });
    }
};
