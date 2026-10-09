# ==============================================================================
# STAGE 1: Dependency Builder (Composer)
# ==============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# ==============================================================================
# STAGE 2: Frontend Asset Builder (Node.js + Vite)
# ==============================================================================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/

RUN npm run build

# ==============================================================================
# STAGE 3: Production Runtime (PHP 8.4-FPM + Nginx on Alpine) - Código limpio
# ==============================================================================
FROM php:8.4-fpm-alpine AS runner

LABEL maintainer="Javier Gálvez <cabocom>"
LABEL description="jG Filmmaker - Suite Web de Pipeline Cinematográfico Asistido por IA"

# Dependencias del sistema, utilidades de runtime y FFmpeg para montaje de tomas
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite-dev \
    postgresql-dev \
    libpq \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    ffmpeg

# Compilar extensiones PHP necesarias (SQLite + PostgreSQL + Rendimiento)
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/include/postgresql/ \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_sqlite \
        pdo_pgsql \
        pgsql \
        opcache \
        pcntl \
        intl \
        bcmath

# Configuración de Nginx, PHP y Supervisord
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar aplicación base (código fuente limpio, sin ofuscación)
COPY . /var/www/html

# Copiar dependencias de producción de composer
COPY --from=vendor /app/vendor /var/www/html/vendor

# Copiar assets frontend compilados por Vite
COPY --from=frontend /app/public/build /var/www/html/public/build

# Limpieza de archivos de desarrollo y prevención de fuga de credenciales
RUN rm -rf /var/www/html/.git \
    /var/www/html/tests \
    /var/www/html/phpunit.xml \
    /var/www/html/node_modules \
    /var/www/html/.env \
    /var/www/html/.env.* \
    /var/www/html/.env.example \
    /var/www/html/.phpunit.result.cache

# Asegurar directorios de almacenamiento, base de datos sqlite y permisos
RUN mkdir -p \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/storage/app/public \
        /var/www/html/bootstrap/cache \
        /var/www/html/database \
        /var/www/html/database_migrations_dist \
        /var/log/nginx \
        /var/log/supervisor \
    && cp -r /var/www/html/database/migrations/* /var/www/html/database_migrations_dist/ 2>/dev/null || true \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/www/html/database_migrations_dist /var/www/html/public /var/log/nginx \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/www/html/database_migrations_dist

EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=10s \
    CMD curl -f http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
