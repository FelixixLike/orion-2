# 🐳 Setup con Docker para Desarrollo

## Requisitos

-   Docker
-   Docker Compose

## 🚀 Inicio Rápido

### 1. Clonar y configurar

```bash
git clone [tu-repo]
cd orion
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
```

### 3. Ejecutar con Docker

```bash
# Para desarrollo (con hot reload)
docker-compose -f docker-compose.dev.yml up -d

# Para producción
docker-compose up -d
```

### 4. Configurar Laravel

```bash
# Generar clave de aplicación
docker-compose exec app php artisan key:generate

# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Crear usuario admin (opcional)
docker-compose exec app php artisan make:filament-user
```

## 🌐 Acceso

-   **Aplicación**: http://localhost:8000
-   **Base de datos**: localhost:5432
-   **Redis**: localhost:6379

## 📝 Comandos Útiles

### Laravel

```bash
# Ejecutar comandos artisan
docker-compose exec app php artisan [comando]

# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Limpiar cache
docker-compose exec app php artisan cache:clear
```

### Node.js (desarrollo)

```bash
# Instalar dependencias
docker-compose exec node npm install

# Compilar assets
docker-compose exec node npm run build

# Modo desarrollo
docker-compose exec node npm run dev
```

### Base de datos

```bash
# Conectar a PostgreSQL
docker-compose exec db psql -U postgres -d orion

# Ver logs de la base de datos
docker-compose logs db
```

## 🔧 Desarrollo

### Hot Reload

-   Los cambios en PHP se reflejan automáticamente
-   Para cambios en JS/CSS, ejecuta: `docker-compose exec node npm run dev`

### Logs

```bash
# Ver todos los logs
docker-compose logs -f

# Logs de un servicio específico
docker-compose logs -f app
```

## 🛑 Parar servicios

```bash
docker-compose down
```

## 🗑️ Limpiar todo

```bash
docker-compose down -v
docker system prune -a
```
