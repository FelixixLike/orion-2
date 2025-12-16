#!/bin/bash
set -e

echo "Iniciando contenedor Orion..."
echo "Esperando por la base de datos en db:5432..."
until pg_isready -h db -p 5432 -U postgres -t 1 >/dev/null 2>&1; do
    echo "Base de datos no disponible. Reintentando..."
    sleep 2
done
echo "Base de datos lista."

mkdir -p storage/framework/{cache,sessions,views,testing} storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "APP_NAME=Orion" > .env
    fi
fi

git config --global --add safe.directory /var/www || true
mkdir -p /var/www/vendor /var/www/vendor/composer /tmp/.composer-cache
chown -R www-data:www-data /var/www 2>/dev/null || true
export COMPOSER_HOME="/tmp/.composer"
composer config -g cache-dir /tmp/.composer-cache || true

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php no encontrado. Ejecutando composer install..."
    ATTEMPT=1
    MAX_ATTEMPTS=3
    while [ $ATTEMPT -le $MAX_ATTEMPTS ]; do
        if command -v composer >/dev/null 2>&1; then
            if gosu www-data composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress; then
                echo "Composer install completado."
                break
            else
                echo "composer install falló (intento $ATTEMPT/$MAX_ATTEMPTS). Limpiando cache..."
                rm -rf /tmp/.composer-cache/* /var/www/vendor/composer/tmp-* 2>/dev/null || true
                sleep 2
            fi
        else
            echo "Composer no disponible. Saltando composer install."
            break
        fi
        ATTEMPT=$((ATTEMPT+1))
    done
    if [ ! -f vendor/autoload.php ] && command -v composer >/dev/null 2>&1; then
        echo "Intento final de composer install como root..."
        composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress || { echo "Composer install falló FATALMENTE. Abortando."; exit 1; }
    fi
    chown -R www-data:www-data vendor 2>/dev/null || true
    
    # Verificación final
    if [ ! -f vendor/autoload.php ]; then
         echo "Error: vendor/autoload.php no encontrado tras instalación. Abortando."
         exit 1
    fi

if [ -f package.json ] && [ ! -d node_modules ]; then
    if command -v npm >/dev/null 2>&1; then
        echo "Instalando dependencias Node (npm ci)..."
        if gosu www-data npm ci --unsafe-perm --silent; then
            echo "npm ci completado como www-data."
        else
            echo "npm ci falló como www-data, intentando como root..."
            npm ci --unsafe-perm --silent || echo "npm ci falló."
        fi
        chown -R www-data:www-data node_modules 2>/dev/null || true
    else
        echo "npm no disponible, omitiendo instalación de node_modules."
    fi
fi

if ! grep -q "APP_KEY=base64:" .env; then
    gosu www-data php artisan key:generate --force || true
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Ejecutando migraciones..."
    for i in {1..5}; do
        if gosu www-data php artisan migrate --force; then
            echo "Migraciones completadas con éxito."
            break
        else
            echo "Error en la migración. Reintentando en 2 segundos... (Intento $i/5)"
            sleep 2
        fi
    done
fi

gosu www-data php artisan config:clear 2>/dev/null || true
gosu www-data php artisan cache:clear 2>/dev/null || true

if [ ! -L public/storage ]; then
    gosu www-data php artisan storage:link || true
fi

if [ "${DEV_SERVE:-false}" = "true" ]; then
    echo "Iniciando servidor de desarrollo en 0.0.0.0:8000"
    exec gosu www-data php artisan serve --host=0.0.0.0 --port=8000
fi

echo "Aplicación lista."
exec "$@"
