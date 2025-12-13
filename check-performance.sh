#!/bin/bash

# Script para verificar y monitorear rendimiento en Filament

echo "🔍 DIAGNÓSTICO DE RENDIMIENTO - ORION"
echo "======================================"
echo ""

# 1. Verificar índices en PostgreSQL
echo "📊 ÍNDICES EN LA BASE DE DATOS:"
docker compose exec db psql -U postgres -d orion -c "
SELECT
    tablename,
    COUNT(*) as total_indexes
FROM pg_indexes
WHERE schemaname = 'public'
GROUP BY tablename
ORDER BY tablename;
" 2>/dev/null || echo "Error al conectar a BD"

echo ""
echo "📈 TAMAÑO DE TABLAS:"
docker compose exec db psql -U postgres -d orion -c "
SELECT
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as tamanio
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 10;
" 2>/dev/null || echo "Error al conectar a BD"

echo ""
echo "💾 USO DE MEMORIA:"
docker stats --no-stream --format "table {{.Container}}\t{{.MemUsage}}" \
    | grep -E "(app|db|redis)" || echo "Error al obtener stats"

echo ""
echo "⚡ ESTADO DEL CACHÉ (Redis):"
docker compose exec redis redis-cli INFO stats 2>/dev/null | grep -E "connected_clients|used_memory" || echo "Redis no disponible"

echo ""
echo "✅ DIAGNÓSTICO COMPLETADO"
echo ""
echo "Para mejora de rendimiento, ver OPTIMIZACION_FILAMENT.md"
