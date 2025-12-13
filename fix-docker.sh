#!/bin/bash
# Script de solución rápida para problemas de Docker en Orion
set -e

echo "🔧 Solucionando problemas de Docker en Orion..."
echo ""

# Detener todo
echo "1️⃣  Deteniendo contenedores..."
docker compose down -v
echo "   ✅ Contenedores detenidos"
echo ""

# Limpiar imágenes
echo "2️⃣  Limpiando imágenes antiguas..."
docker rmi orion-app orion-worker 2>/dev/null || echo "   ℹ️  No hay imágenes antiguas para eliminar"
echo "   ✅ Imágenes limpiadas"
echo ""

# Limpiar cache
echo "3️⃣  Limpiando cache de Docker..."
docker builder prune -f
echo "   ✅ Cache limpiado"
echo ""

# Exportar UID/GID
echo "4️⃣  Configurando permisos..."
export USER_ID=$(id -u)
export GROUP_ID=$(id -g)
echo "   UID: $USER_ID"
echo "   GID: $GROUP_ID"
echo "   ✅ Permisos configurados"
echo ""

# Reconstruir
echo "5️⃣  Reconstruyendo imágenes (esto puede tardar varios minutos)..."
docker compose build --no-cache
echo "   ✅ Imágenes reconstruidas"
echo ""

# Levantar
echo "6️⃣  Levantando contenedores..."
docker compose up -d
echo "   ✅ Contenedores levantados"
echo ""

# Esperar un poco
echo "7️⃣  Esperando que los contenedores inicien..."
sleep 5
echo ""

# Verificar estado
echo "8️⃣  Verificando estado de los contenedores..."
docker compose ps
echo ""

# Ver logs
echo "9️⃣  Verificando que todo esté funcionando..."
echo ""
sleep 10

# Verificar estado
docker compose ps
echo ""

echo "✅ ¡Instalación completada!"
echo ""
echo "📋 Servicios disponibles:"
echo "   🌐 Aplicación: http://localhost:8000"
echo "   🔥 Vite HMR:   http://localhost:5173"
echo "   🗄️  PostgreSQL: localhost:5433"
echo "   💾 Redis:      localhost:6380"
echo ""
echo "📝 Para ver los logs en tiempo real:"
echo "   docker compose logs -f app"
echo ""
