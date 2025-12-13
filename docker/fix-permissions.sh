#!/bin/bash
# Script para corregir permisos de archivos creados por Docker

# Obtener UID/GID del usuario actual
HOST_UID=${USER_ID:-$(id -u)}
HOST_GID=${GROUP_ID:-$(id -g)}

echo "🔐 Corrigiendo permisos de archivos creados por Docker..."
echo "Usuario del host: UID=$HOST_UID GID=$HOST_GID"

# Ajustar permisos de archivos comunes que pueden ser creados por artisan
docker compose exec app bash -c "
    # Archivos en app/
    find app -type f -user www-data -exec chmod 644 {} \; 2>/dev/null || true
    
    # Migraciones
    find database/migrations -type f -user www-data -exec chmod 644 {} \; 2>/dev/null || true
    
    # Seeders
    find database/seeders -type f -user www-data -exec chmod 644 {} \; 2>/dev/null || true
    
    # Factories
    find database/factories -type f -user www-data -exec chmod 644 {} \; 2>/dev/null || true
    
    # Routes
    find routes -type f -user www-data -exec chmod 644 {} \; 2>/dev/null || true
    
    # Config
    find config -type f -user www-data -exec chmod 644 {} \; 2>/dev/null || true
    
    # Tests
    find tests -type f -user www-data -exec chmod 644 {} \; 2>/dev/null || true
"

# También ajustar desde el host usando chown si es necesario
# Nota: Esto solo funciona si el UID/GID coinciden entre host y contenedor
if [ "$HOST_UID" = "1000" ]; then
    echo "✅ Permisos ajustados (UID coincide: 1000)"
else
    echo "⚠️  UID del host ($HOST_UID) no coincide con UID del contenedor (1000)"
    echo "   Los archivos pueden tener permisos incorrectos"
    echo "   Ejecuta: sudo chown -R $HOST_UID:$HOST_GID app/ database/"
fi

