#!/bin/bash
# Wrapper para artisan que ajusta permisos después de crear archivos

# Obtener UID/GID del usuario actual en el host
HOST_UID=${USER_ID:-$(id -u)}
HOST_GID=${GROUP_ID:-$(id -g)}

# Ejecutar el comando artisan
docker compose exec -u www-data app php artisan "$@"
EXIT_CODE=$?

# Si el comando fue exitoso y creó archivos en directorios específicos, ajustar permisos
if [ $EXIT_CODE -eq 0 ]; then
    # Solo ajustar permisos si el comando involucra crear archivos
    case "$1" in
        make:model|make:migration|make:controller|make:resource|make:filament-resource|make:livewire|make:command|make:job|make:mail|make:notification|make:request|make:service|make:provider|make:middleware|make:factory|make:seeder|make:test)
            echo "🔐 Ajustando permisos de archivos creados..."
            
            # Ajustar permisos para archivos creados por www-data (que tiene UID 1000, igual que el usuario del host)
            # En el contenedor, www-data tiene UID 1000, pero en el host esos archivos aparecen como pertenecientes al usuario con UID 1000
            # Solo necesitamos ajustar los permisos de escritura
            docker compose exec app bash -c "
                find app database/migrations database/seeders database/factories routes config tests -type f -newer /tmp/.artisan-wrapper-timestamp -user www-data -exec chmod 644 {} \; 2>/dev/null || true
                touch /tmp/.artisan-wrapper-timestamp
            " || true
            
            echo "✅ Permisos ajustados"
            ;;
    esac
fi

exit $EXIT_CODE

