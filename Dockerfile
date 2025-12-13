FROM php:8.2-fpm

# --- 1. Variables y Definición de Usuario ---
ARG USER_ID=1000
ARG GROUP_ID=1000

ENV USER_ID=${USER_ID}
ENV GROUP_ID=${GROUP_ID}
ENV RUN_MIGRATIONS=true
ENV DEV_SERVE=false

WORKDIR /var/www

# --- 2. Instalación de Dependencias del Sistema ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl ca-certificates gnupg2 libpng-dev libonig-dev libxml2-dev zip unzip libpq-dev \
    libicu-dev libzip-dev postgresql-client gosu build-essential \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# --- 3. Instalación de Extensiones PHP y Composer ---
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV HOME=/tmp

# --- 4. Configuración de Usuario y Permisos ---
RUN if [ "${USER_ID}" -ne 0 ] && [ "${GROUP_ID}" -ne 0 ]; then \
        usermod -u "${USER_ID}" www-data && \
        groupmod -g "${GROUP_ID}" www-data; \
    fi \
    && chown -R www-data:www-data /var/www

# --- 5. Configuración PHP-FPM ---
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# --- 6. Script Entrypoint ---
RUN cat > /usr/local/bin/entrypoint.sh << 'EOT'
#!/bin/bash
set -e

echo "Iniciando contenedor Orion..."
echo "Esperando por la base de datos en db:5432..."
until pg_isready -h db -p 5432 -U postgres -t 1 >/dev/null 2>&1; do
    echo "Base de datos no disponible. Reintentando..."
    sleep 2
done
echo "Base de datos lista."

mkdir -p \
    storage/framework/{cache,sessions,views,testing} \
    storage/logs \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

# Asegurar .env
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "APP_NAME=Orion" > .env
    fi
fi

# Configuración segura para Composer
git config --global --add safe.directory /var/www || true
mkdir -p /var/www/vendor /var/www/vendor/composer /tmp/.composer-cache
chown -R www-data:www-data /var/www 2>/dev/null || true
export COMPOSER_HOME="/tmp/.composer"
composer config -g cache-dir /tmp/.composer-cache || true

# Instalar dependencias PHP si falta vendor/autoload.php
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
                echo "composer install falló (intento $ATTEMPT/$MAX_ATTEMPTS). Limpiando cache y reintentando..."
                rm -rf /tmp/.composer-cache/* /var/www/vendor/composer/tmp-* 2>/dev/null || true
                sleep 2
            fi
        else
            echo "Composer no disponible en el contenedor. Saltando composer install."
            break
        fi
        ATTEMPT=$((ATTEMPT+1))
    done

    if [ ! -f vendor/autoload.php ] && command -v composer >/dev/null 2>&1; then
        echo "Intento final de composer install como root..."
        composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress || echo "Composer install falló por completo."
    fi

    chown -R www-data:www-data vendor 2>/dev/null || true
fi

# Instalar dependencias Node si es necesario
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

# Generar APP_KEY si no existe
if ! grep -q "APP_KEY=base64:" .env; then
    gosu www-data php artisan key:generate --force || true
fi

# Migraciones (solo si RUN_MIGRATIONS=true)
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

# Modo desarrollo opcional
if [ "${DEV_SERVE:-false}" = "true" ]; then
    echo "Iniciando servidor de desarrollo en 0.0.0.0:8000"
    exec gosu www-data php artisan serve --host=0.0.0.0 --port=8000
fi

echo "Aplicación lista."
exec "$@"
EOT

RUN chmod +x /usr/local/bin/entrypoint.sh

# --- 7. Configuración Final del Contenedor ---
EXPOSE 9000
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
