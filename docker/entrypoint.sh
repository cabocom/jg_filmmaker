#!/bin/sh
set -e
umask 022

echo "🎬 [jG Filmmaker] Inicializando contenedor web..."

# Crear directorios de cache, vistas, base de datos, logs y medios
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache \
         /var/www/html/resources/views \
         /var/www/html/database

# Sincronizar migraciones desde la imagen hacia database/migrations si existe backup
if [ -d /var/www/html/database_migrations_dist ]; then
    echo "📋 [Migraciones] Sincronizando archivos de migración..."
    mkdir -p /var/www/html/database/migrations
    cp -u /var/www/html/database_migrations_dist/*.php /var/www/html/database/migrations/ 2>/dev/null || cp -n /var/www/html/database_migrations_dist/*.php /var/www/html/database/migrations/ 2>/dev/null || cp -r /var/www/html/database_migrations_dist/* /var/www/html/database/migrations/ 2>/dev/null || true
fi

# Asegurar existencia de SQLite solo si se usa sqlite
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "📄 [SQLite] Creando archivo database/database.sqlite inicial..."
        touch /var/www/html/database/database.sqlite
    fi
    chmod 664 /var/www/html/database/database.sqlite 2>/dev/null || true
fi

# Ajustar permisos de directorios de Laravel y SQLite
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Asegurar directorios temporales de Nginx y permisos para www-data
mkdir -p /var/lib/nginx/tmp/client_body \
         /var/lib/nginx/tmp/fastcgi \
         /var/lib/nginx/tmp/proxy \
         /var/lib/nginx/tmp/scgi \
         /var/lib/nginx/tmp/uwsgi \
         /var/log/nginx
chown -R www-data:www-data /var/lib/nginx /var/log/nginx
chmod -R 775 /var/lib/nginx /var/log/nginx

# Limpiar manifiestos residuales de bootstrap
rm -f /var/www/html/bootstrap/cache/*.php

# Determinar entorno de ejecución
CURRENT_ENV="${APP_ENV:-production}"

# Validación o generación de APP_KEY
if [ -z "$APP_KEY" ]; then
    if [ "$CURRENT_ENV" = "production" ]; then
        echo "❌ [Seguridad] APP_KEY obligatoria no configurada en producción. Abortando arranque."
        exit 1
    else
        echo "⚠️ [Seguridad] APP_KEY no configurada. Generando clave temporal de desarrollo..."
        php artisan key:generate --force || true
    fi
fi

# Desactivar APP_DEBUG en producción si no está explicitamente forzado
if [ "$CURRENT_ENV" = "production" ] && [ "$APP_DEBUG" = "true" ]; then
    echo "⚠️ [Seguridad] APP_DEBUG detectado como 'true' en producción. Forzando APP_DEBUG=false."
    export APP_DEBUG="false"
fi

# Enlace simbólico de storage público para tomas de vídeo
echo "🔗 Verificando enlace simbólico de almacenamiento público (storage:link)..."
php artisan storage:link || true

# Descubrir paquetes instalados de Laravel
echo "🔍 Descubriendo paquetes de Laravel..."
php artisan package:discover --ansi || true

# Publicar assets estáticos de Livewire
echo "🎨 Publicando assets estáticos de Livewire..."
php artisan livewire:publish --assets || true

# Migraciones y seeders de base de datos
if [ "$RUN_MIGRATIONS" = "true" ] || [ "$CURRENT_ENV" = "production" ]; then
    echo "📦 Ejecutando migraciones de base de datos..."
    php artisan migrate --force
    if [ "$SEED_DEMO" = "true" ]; then
        if [ "$CURRENT_ENV" = "production" ]; then
            echo "⚠️ [Seguridad] SEED_DEMO=true ignorado en producción para proteger cuentas de usuario."
        else
            echo "🌱 Ejecutando seeders de demostración..."
            php artisan db:seed --force
        fi
    fi
fi

# Limpiar y preparar caché de vistas
echo "⚡ Limpiando y preparando caché de Laravel..."
rm -rf /var/www/html/storage/framework/views/* /var/www/html/storage/framework/cache/data/*
php artisan optimize:clear || true
php artisan view:cache || true

# Permisos finales sobre public, storage y bootstrap/cache
chown -R www-data:www-data /var/www/html/public /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

echo "✅ [jG Filmmaker] Contenedor listo. Iniciando supervisord..."
exec "$@"
