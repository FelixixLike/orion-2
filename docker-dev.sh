#!/bin/bash
# Script helper para gestionar Docker en desarrollo

# Exportar UID y GID del usuario actual
export USER_ID=$(id -u)
export GROUP_ID=$(id -g)

case "$1" in
    up)
        echo "🚀 Levantando contenedores..."
        docker compose up -d
        ;;
    down)
        echo "🛑 Deteniendo contenedores..."
        docker compose down
        ;;
    build)
        echo "🔨 Construyendo imagen..."
        docker compose build --no-cache
        ;;
    rebuild)
        echo "🔨 Reconstruyendo desde cero..."
        docker compose down -v
        docker compose build --no-cache
        docker compose up -d
        ;;
    restart)
        echo "🔄 Reiniciando contenedores..."
        docker compose restart
        ;;
    logs)
        docker compose logs -f ${2:-app}
        ;;
    shell)
        docker compose exec app bash
        ;;
    artisan)
        shift
        # Usar el wrapper si existe, sino ejecutar directamente
        if [ -f "docker/artisan-wrapper.sh" ]; then
            bash docker/artisan-wrapper.sh "$@"
        else
            docker compose exec -u www-data app php artisan "$@"
        fi
        ;;
    composer)
        shift
        docker compose exec -u www-data app composer "$@"
        ;;
    fix-permissions)
        # Usar el script de fix-permissions si existe
        if [ -f "docker/fix-permissions.sh" ]; then
            bash docker/fix-permissions.sh
        else
            echo "🔐 Corrigiendo permisos..."
            docker compose exec app chown -R www-data:www-data storage bootstrap/cache
            docker compose exec app chmod -R ug+rwx storage bootstrap/cache
            echo "✅ Permisos corregidos"
        fi
        ;;
    *)
        echo "Uso: $0 {up|down|build|rebuild|restart|logs|shell|artisan|composer|fix-permissions}"
        echo ""
        echo "Comandos disponibles:"
        echo "  up              - Levantar contenedores"
        echo "  down            - Detener contenedores"
        echo "  build           - Construir imagen"
        echo "  rebuild         - Reconstruir desde cero (elimina volúmenes)"
        echo "  restart         - Reiniciar contenedores"
        echo "  logs [servicio] - Ver logs (default: app)"
        echo "  shell           - Abrir shell en el contenedor"
        echo "  artisan [cmd]   - Ejecutar comando artisan"
        echo "  composer [cmd]  - Ejecutar comando composer"
        echo "  fix-permissions - Corregir permisos manualmente"
        exit 1
        ;;
esac

