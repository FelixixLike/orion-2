<?php

namespace App\Domain\Mail\Services;

use App\Domain\Mail\Models\MailSetting;
use Illuminate\Support\Facades\Artisan;

class MailConfigService
{
    /**
     * Actualiza las configuraciones de correo y las guarda en .env.
     *
     * @param array $data
     * @return MailSetting
     */
    public function updateMailConfig(array $data): MailSetting
    {
        $setting = MailSetting::getInstance();
        $setting->fill($data);
        $setting->save();
        
        // Actualizar .env
        $this->updateEnvFile($setting);
        
        // Limpiar cache de configuración
        Artisan::call('config:clear');
        
        return $setting;
    }
    
    /**
     * Actualiza el archivo .env con las configuraciones de correo.
     *
     * @param MailSetting $setting
     * @return void
     */
    private function updateEnvFile(MailSetting $setting): void
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            return;
        }
        
        $envContent = file_get_contents($envPath);
        
        // Lista de variables a actualizar (solo SMTP)
        $variables = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => $setting->host ?? '',
            'MAIL_PORT' => $setting->port ?? '',
            'MAIL_USERNAME' => $setting->from_address ?? '',
            'MAIL_PASSWORD' => $setting->password ?? '',
            'MAIL_ENCRYPTION' => $setting->encryption ?? '',
            'MAIL_FROM_ADDRESS' => $setting->from_address,
            'MAIL_FROM_NAME' => $setting->from_name,
        ];
        
        foreach ($variables as $key => $value) {
            // Escapar comillas y caracteres especiales
            $escapedValue = $this->escapeEnvValue($value);
            
            // Buscar si la variable ya existe
            $pattern = "/^{$key}=.*/m";
            
            if (preg_match($pattern, $envContent)) {
                // Reemplazar si existe
                $envContent = preg_replace($pattern, "{$key}={$escapedValue}", $envContent);
            } else {
                // Agregar si no existe (después de otras variables MAIL_ si existen)
                if (preg_match("/^MAIL_/m", $envContent) && str_starts_with($key, 'MAIL_')) {
                    // Insertar después de la última variable MAIL_
                    $envContent = preg_replace(
                        "/(^MAIL_[^\n]*\n)/m",
                        "$1{$key}={$escapedValue}\n",
                        $envContent,
                        1
                    );
                } else {
                    // Agregar al final
                    $envContent .= "\n{$key}={$escapedValue}\n";
                }
            }
        }
        
        file_put_contents($envPath, $envContent);
    }
    
    /**
     * Escapa un valor para uso en archivo .env.
     *
     * @param string|null $value
     * @return string
     */
    private function escapeEnvValue(?string $value): string
    {
        if (empty($value)) {
            return 'null';
        }
        
        // Si contiene espacios o caracteres especiales, usar comillas
        if (preg_match('/[\s#=]/', $value) || str_starts_with($value, '"')) {
            // Ya está entre comillas o necesita comillas
            return '"' . addslashes($value) . '"';
        }
        
        return $value;
    }
}

