# Script para gestionar Orion fácilmente desde PowerShell
# Uso: .\orion.ps1 [comando]

param(
    [string]$comando = "help"
)

# Colores
$verde = "Green"
$azul = "Cyan"
$rojo = "Red"
$amarillo = "Yellow"

function MostrarAyuda {
    Write-Host ""
    Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor $verde
    Write-Host "║                 COMANDOS DISPONIBLES - ORION                   ║" -ForegroundColor $verde
    Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor $verde
    Write-Host ""
    Write-Host "Uso: .\orion.ps1 [comando]" -ForegroundColor $azul
    Write-Host ""
    Write-Host "COMANDOS DOCKER:" -ForegroundColor $amarillo
    Write-Host "  up           - Iniciar los contenedores"
    Write-Host "  down         - Parar los contenedores"
    Write-Host "  restart      - Reiniciar los contenedores"
    Write-Host "  ps           - Ver estado de los contenedores"
    Write-Host "  logs         - Ver logs en tiempo real"
    Write-Host "  build        - Reconstruir imágenes y levantar"
    Write-Host ""
    Write-Host "COMANDOS LARAVEL:" -ForegroundColor $amarillo
    Write-Host "  migrate      - Ejecutar migraciones"
    Write-Host "  rollback     - Rollback de migraciones"
    Write-Host "  status       - Ver estado de migraciones"
    Write-Host "  seed         - Ejecutar seeders"
    Write-Host "  cache:clear  - Limpiar caché"
    Write-Host "  tinker       - Abrir consola interactiva"
    Write-Host ""
    Write-Host "COMANDOS FRONTEND:" -ForegroundColor $amarillo
    Write-Host "  npm:install  - Instalar dependencias con Bun"
    Write-Host "  npm:dev      - Ejecutar dev server con hot reload"
    Write-Host "  npm:build    - Compilar assets para producción"
    Write-Host ""
    Write-Host "BASE DE DATOS:" -ForegroundColor $amarillo
    Write-Host "  db:connect   - Conectar a PostgreSQL"
    Write-Host "  db:backup    - Hacer backup de la BD"
    Write-Host ""
    Write-Host "ÚTILES:" -ForegroundColor $amarillo
    Write-Host "  bash         - Abrir terminal en el contenedor app"
    Write-Host "  clean        - Limpiar todo y reiniciar"
    Write-Host "  status       - Ver estado de todo"
    Write-Host "  help         - Mostrar esta ayuda"
    Write-Host ""
}

function Ejecutar {
    param([string]$cmd)
    Write-Host "▶ $cmd" -ForegroundColor $azul
    Invoke-Expression $cmd
}

switch ($comando) {
    # DOCKER
    "up" {
        Ejecutar "docker-compose up -d"
    }
    "down" {
        Ejecutar "docker-compose down"
    }
    "restart" {
        Ejecutar "docker-compose restart"
    }
    "ps" {
        Write-Host ""
        docker-compose ps
        Write-Host ""
    }
    "logs" {
        Ejecutar "docker-compose logs -f app"
    }
    "build" {
        Ejecutar "docker-compose up -d --build"
    }

    # LARAVEL
    "migrate" {
        Ejecutar "docker-compose exec -u www-data app php artisan migrate --force"
    }
    "rollback" {
        Ejecutar "docker-compose exec -u www-data app php artisan migrate:rollback"
    }
    "status" {
        if ($args.Count -eq 0 -or $args[0] -eq "all") {
            Write-Host "Estado de Migraciones:" -ForegroundColor $azul
            docker-compose exec -u www-data app php artisan migrate:status
        } else {
            MostrarAyuda
        }
    }
    "seed" {
        Ejecutar "docker-compose exec -u www-data app php artisan db:seed"
    }
    "cache:clear" {
        Ejecutar "docker-compose exec -u www-data app php artisan cache:clear"
    }
    "tinker" {
        Ejecutar "docker-compose exec -u www-data app php artisan tinker"
    }

    # FRONTEND
    "npm:install" {
        Ejecutar "docker-compose exec bun bun install"
    }
    "npm:dev" {
        Ejecutar "docker-compose exec bun bun run dev --host 0.0.0.0"
    }
    "npm:build" {
        Ejecutar "docker-compose exec bun bun run build"
    }

    # BASE DE DATOS
    "db:connect" {
        Ejecutar "docker-compose exec db psql -U postgres -d orion"
    }
    "db:backup" {
        $fecha = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
        $archivo = "backup_$fecha.sql"
        Ejecutar "docker-compose exec db pg_dump -U postgres orion > $archivo"
        Write-Host "✅ Backup creado: $archivo" -ForegroundColor $verde
    }

    # ÚTILES
    "bash" {
        Ejecutar "docker-compose exec -u www-data app bash"
    }
    "clean" {
        Write-Host "Limpiando todo..." -ForegroundColor $rojo
        Ejecutar "docker-compose down -v"
        Ejecutar "docker system prune -a"
        Write-Host "Levantando nuevamente..." -ForegroundColor $verde
        Ejecutar "docker-compose up -d --build"
    }
    "status" {
        Write-Host ""
        Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor $verde
        Write-Host "ESTADO DEL PROYECTO ORION" -ForegroundColor $verde
        Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor $verde
        Write-Host ""

        Write-Host "📊 Contenedores:" -ForegroundColor $azul
        docker-compose ps

        Write-Host ""
        Write-Host "🌐 URLs de Acceso:" -ForegroundColor $azul
        Write-Host "  • Aplicación: http://localhost:8000"
        Write-Host "  • Vite Dev: http://localhost:5173"
        Write-Host "  • Reverb WS: http://localhost:8080"
        Write-Host "  • PostgreSQL: localhost:5433"
        Write-Host "  • Redis: localhost:6380"
        Write-Host ""
    }
    "help" {
        MostrarAyuda
    }
    default {
        Write-Host "❌ Comando desconocido: $comando" -ForegroundColor $rojo
        Write-Host ""
        MostrarAyuda
    }
}
