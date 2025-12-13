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
        Schema::table('mail_settings', function (Blueprint $table) {
            // Eliminar columnas de servicios de terceros (solo usamos SMTP)
            $table->dropColumn([
                'postmark_token',
                'resend_key',
                'ses_key',
                'ses_secret',
                'ses_region',
            ]);
            
            // Actualizar mailer para que siempre sea smtp
            $table->string('mailer')->default('smtp')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            // Restaurar columnas eliminadas
            $table->text('postmark_token')->nullable()->after('from_name');
            $table->text('resend_key')->nullable()->after('postmark_token');
            $table->text('ses_key')->nullable()->after('resend_key');
            $table->text('ses_secret')->nullable()->after('ses_key');
            $table->string('ses_region')->nullable()->default('us-east-1')->after('ses_secret');
        });
    }
};
