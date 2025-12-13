# 🚀 Orion - Laravel Application

Una aplicación web moderna construida con Laravel, Filament y Docker para un desarrollo fácil y escalable.

## 🛠️ Stack Tecnológico

-   **Backend**: Laravel 12 + PHP 8.2
-   **Frontend**: Vite + TailwindCSS + Alpine.js
-   **Admin Panel**: Filament 4
-   **Base de Datos**: PostgreSQL 14
-   **Contenedores**: Docker + Docker Compose
-   **Package Manager**: Bun

## 📋 Requisitos

-   Docker
-   Docker Compose

**¡Eso es todo!** No necesitas instalar PHP, Composer, Node.js, PostgreSQL ni ninguna otra dependencia local.

## 🚀 Inicio Rápido

### 1. Clonar el proyecto

```bash
git clone https://github.com/youesei/orion
cd orion
```

### 2. Crear archivo de configuración

```bash
# Crear archivo .env para Laravel
cat > .env << 'EOF'
APP_NAME=Orion
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=orion
DB_USERNAME=postgres
DB_PASSWORD=postgres
EOF
```

### 3. Ejecutar con Docker

```bash
# Levantar todos los servicios con docker
docker-compose up -d
```

```bash
# Levantar todos los servicios con npm
npm run docker:up
```

### 4. Configurar Laravel

```bash
# Generar clave de aplicación
docker-compose exec app php artisan key:generate

# Ejecutar migraciones (esto crea las tablas necesarias incluyendo 'sessions')
docker-compose exec app php artisan migrate

# Crear usuario admin (opcional)
docker-compose exec app php artisan make:filament-user
```

**⚠️ Nota importante**: Si ves el error `relation "sessions" does not exist`, significa que las migraciones no se han ejecutado. Simplemente ejecuta `docker-compose exec app php artisan migrate` para solucionarlo.

## 🌐 Acceso a la Aplicación

-   **Aplicación Web**: http://localhost:8000
-   **Base de Datos**: localhost:5433 (usuario: `postgres`, password: `postgres`)
-   **Vite Dev Server**: http://localhost:5173

## 📝 Comandos Útiles

### Laravel

```bash
# Ejecutar comandos artisan
docker-compose exec app php artisan [comando]

# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Limpiar cache
docker-compose exec app php artisan cache:clear

# Ver logs de Laravel
docker-compose exec app php artisan pail
```

### Frontend (Bun)

```bash
# Instalar dependencias
docker-compose exec bun bun install

# Modo desarrollo (hot reload)
docker-compose exec bun bun run dev

# Compilar para producción
docker-compose exec bun bun run build

# Lintear archivos JS/TS
docker-compose exec bun bun run lint

# Lintear y arreglar automáticamente
docker-compose exec bun bun run lint:fix
```

### Formateo y Linting

#### 🔧 Herramientas de Código

- **PHP**: Laravel Pint + PHP CS Fixer
- **JavaScript/TypeScript**: Biome
- **Blade**: PHP CS Fixer (básico) + extensiones de editor

#### 📋 Comandos Docker

```bash
# Formatear archivos PHP (Laravel Pint)
docker-compose exec app ./vendor/bin/pint

# Ver qué cambios haría (dry-run)
docker-compose exec app ./vendor/bin/pint --test

# Formatear con PHP CS Fixer
docker-compose exec app ./vendor/bin/php-cs-fixer fix

# Formatear todo (PHP + JS)
npm run format:all

# Solo formatear PHP
npm run format:php

# Solo lintear JS
npm run lint
```

#### 🎯 Comandos NPM/Bun

```bash
# Formatear PHP
bun run format:php

# Formatear PHP (test mode)
bun run format:php:test

# Formatear con PHP CS Fixer
bun run format:php:cs

# Formatear con PHP CS Fixer (test mode)
bun run format:php:cs:test

# Lintear JavaScript/TypeScript
bun run lint

# Lintear y arreglar automáticamente
bun run lint:fix

# Formatear todo (PHP + JS)
bun run format:all
```

#### 🔌 Configuración de Editores

##### VS Code / Cursor
```json
// .vscode/settings.json
{
  "php-cs-fixer.executablePath": "./vendor/bin/php-cs-fixer",
  "php-cs-fixer.rules": "@PSR12",
  "biome.lspBin": "./node_modules/@biomejs/biome/bin/biome",
  "editor.formatOnSave": true,
  "editor.codeActionsOnSave": {
    "source.fixAll": true
  }
}
```

**Extensiones recomendadas:**
- PHP CS Fixer
- Laravel Pint
- Biome
- Laravel Blade Snippets

##### PhpStorm
1. **PHP CS Fixer**: `Settings > Tools > External Tools`
2. **Biome**: `Settings > Languages & Frameworks > JavaScript > Code Quality Tools > Biome`
3. **Laravel Pint**: `Settings > Tools > External Tools`

##### Sublime Text
- PHP CS Fixer: Package Control → "PHP CS Fixer"
- Biome: Package Control → "Biome"

#### 📁 Archivos de Configuración

- **`.php-cs-fixer.php`**: Configuración de PHP CS Fixer
- **`.blade-formatter.json`**: Configuración de Blade Formatter
- **`biome.json`**: Configuración de Biome (en package.json)

#### 🚀 Integración con Git

```bash
# Pre-commit hook (opcional)
# .git/hooks/pre-commit
#!/bin/sh
bun run format:all
git add .
```

### Base de Datos

```bash
# Conectar a PostgreSQL
docker-compose exec db psql -U postgres -d orion

# Ver logs de la base de datos
docker-compose logs db

# Backup de la base de datos
docker-compose exec db pg_dump -U postgres orion > backup.sql
```

### Docker

```bash
# Ver logs de todos los servicios
docker-compose logs -f

# Ver logs de un servicio específico
docker-compose logs -f app

# Reiniciar un servicio
docker-compose restart app

# Parar todos los servicios
docker-compose down

# Parar y eliminar volúmenes (⚠️ CUIDADO: Borra datos)
docker-compose down -v
```

## 🔧 Desarrollo

### Hot Reload

-   **PHP**: Los cambios se reflejan automáticamente
-   **Frontend**: Ejecuta `docker-compose exec bun bun run dev` para hot reload de JS/CSS

### Estructura del Proyecto

```
orion/
├── app/                 # Código de la aplicación Laravel
├── database/           # Migraciones y seeders
├── docker/            # Configuraciones de Docker
│   ├── nginx/         # Configuración de Nginx
│   └── php/           # Configuración de PHP
├── public/            # Archivos públicos
├── resources/         # Vistas, assets, etc.
├── routes/            # Rutas de la aplicación
├── storage/           # Logs, cache, etc.
├── docker-compose.yml # Configuración de Docker Compose
├── Dockerfile         # Imagen de la aplicación
└── package.json       # Dependencias de frontend
```

## 🐳 Servicios Docker

| Servicio | Imagen        | Puerto | Descripción                     |
| -------- | ------------- | ------ | ------------------------------- |
| `app`    | PHP 8.2-FPM   | 9000   | Aplicación Laravel              |
| `nginx`  | Nginx Alpine  | 8000   | Servidor web                    |
| `db`     | PostgreSQL 14 | 5433   | Base de datos                   |
| `bun`    | Bun Alpine    | 5173   | Servidor de desarrollo frontend |

## 🚨 Solución de Problemas

### Error: `relation "sessions" does not exist`

Este error indica que las migraciones no se han ejecutado:

```bash
# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Si persiste, verificar estado de migraciones
docker-compose exec app php artisan migrate:status
```

### Error de conexión a la base de datos

```bash
# Verificar que PostgreSQL esté corriendo
docker-compose ps

# Reiniciar la base de datos
docker-compose restart db

# Ver logs de la base de datos
docker-compose logs db
```

### Error de permisos

```bash
# Reconfigurar permisos
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 775 /var/www/storage
```

### Limpiar todo y empezar de nuevo

```bash
# Parar y eliminar todo
docker-compose down -v
docker system prune -a

# Volver a empezar
docker-compose up -d
```

## 📚 Documentación Adicional

-   [Laravel Documentation](https://laravel.com/docs)
-   [Filament Documentation](https://filamentphp.com/docs)
-   [Docker Documentation](https://docs.docker.com/)

## 🤝 Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia y Derechos de Autor

**ESTE ES UN SOFTWARE PRIVADO. QUEDA PROHIBIDO SU USO SIN AUTORIZACIÓN.**

Este proyecto es propiedad intelectual exclusiva de:
- Andrés Felipe Martínez González
- Nelson Steven Reina Moreno
- Gissel Tatiana Parrado Moreno

El uso no autorizado de este código, total o parcial, constituye un delito.  
Consulte el archivo [LICENSE.md](LICENSE.md) para ver los términos legales completos y las restricciones de uso.

**Derechos reservados © 2025**
