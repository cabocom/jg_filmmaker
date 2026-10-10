#!/usr/bin/env bash
# ==============================================================================
# Script de Despliegue Manual en Producción - films.javiergalvez.com
# Servidor: webdev.cabocom.com | Directorio: /cabocom/web/apps/films.javiergalvez.com
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "=================================================================="
echo "🎬 DESPLIEGUE EN PRODUCCIÓN: jG Filmmaker (films.javiergalvez.com)"
echo "=================================================================="
echo ""

if [ ! -f ".env" ]; then
    echo "❌ Error: Archivo .env no encontrado en $SCRIPT_DIR."
    exit 1
fi

# Cargar variables de entorno
set -a
# shellcheck disable=SC1091
source .env
set +a

# 1. Pull de imágenes desde el registro privado
echo "▶ [1/5] Descargando últimas imágenes desde registry.cabocom.net..."
docker compose pull
echo "   ✓ Imágenes actualizadas."

# 2. Despliegue y recreación ordenada
echo "▶ [2/5] Desplegando y recreando contenedores..."
docker compose up -d --remove-orphans
echo "   ✓ Contenedores iniciados en segundo plano."

# 3. Esperar que los servicios estén listos (Health Check)
echo "▶ [3/5] Esperando a que los servicios estén listos y saludables..."

FILMMAKER_PORT="8924"

# Esperar a PostgreSQL de Filmmaker (máx 30s)
echo "   - Verificando PostgreSQL de Filmmaker..."
ATTEMPTS=0
MAX_ATTEMPTS=30
until docker compose exec -T jg_filmmaker_postgres pg_isready -U "${DB_USERNAME:-postgres}" -d "${DB_DATABASE:-jg_filmmaker}" &>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
        echo "   ⚠ Timeout esperando PostgreSQL de Filmmaker."
        break
    fi
    sleep 1
done
if [ "$ATTEMPTS" -lt "$MAX_ATTEMPTS" ]; then
    echo "   ✓ PostgreSQL de Filmmaker listo."
fi

# Esperar a PostgreSQL del Agente (máx 30s)
echo "   - Verificando PostgreSQL del Agente..."
ATTEMPTS=0
until docker compose exec -T jg_agente_postgres_filmmaker pg_isready -U "${JG_AGENTE_POSTGRES_USER:-postgres}" -d "${JG_AGENTE_POSTGRES_DB:-jg_agente_filmmaker}" &>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
        echo "   ⚠ Timeout esperando PostgreSQL del Agente."
        break
    fi
    sleep 1
done
if [ "$ATTEMPTS" -lt "$MAX_ATTEMPTS" ]; then
    echo "   ✓ PostgreSQL del Agente listo."
fi

# Esperar al Agente Cognitivo (máx 35s)
echo "   - Verificando Agente Cognitivo jg_agente_filmmaker..."
ATTEMPTS=0
MAX_ATTEMPTS=35
until docker compose exec -T jg_filmmaker curl -sf "http://jg_agente_filmmaker:8000/health" &>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
        echo "   ⚠ Timeout esperando salud de jg_agente_filmmaker."
        break
    fi
    sleep 1
done
if [ "$ATTEMPTS" -lt "$MAX_ATTEMPTS" ]; then
    echo "   ✓ Agente Cognitivo saludable con plugin filmmaker activo."
fi

# Esperar a jG Filmmaker Web (puerto 8924) (máx 35s)
echo "   - Verificando jG Filmmaker Web (puerto $FILMMAKER_PORT)..."
ATTEMPTS=0
until curl -sf "http://127.0.0.1:${FILMMAKER_PORT}/up" &>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
        echo "   ⚠ Timeout esperando jG Filmmaker en puerto $FILMMAKER_PORT."
        break
    fi
    sleep 1
done
if [ "$ATTEMPTS" -lt "$MAX_ATTEMPTS" ]; then
    echo "   ✓ jG Filmmaker listo y respondiendo en http://127.0.0.1:${FILMMAKER_PORT}."
fi

# 4. Migraciones de Base de Datos y Optimización
echo "▶ [4/5] Aplicando migraciones y optimizaciones de caché..."
echo "   - Migraciones troncales del agente cognitivo (Alembic)..."
docker compose exec -T jg_agente_filmmaker alembic upgrade head || true

echo "   - Migraciones de jG Filmmaker en PostgreSQL..."
docker compose exec -T jg_filmmaker php artisan migrate --force || true

echo "   - Limpieza y precalentamiento de cachés de Laravel..."
docker compose exec -T jg_filmmaker php artisan optimize:clear || true
docker compose exec -T jg_filmmaker php artisan view:cache || true
echo "   ✓ Migraciones y cachés actualizadas."

# 5. Resumen del estado de los contenedores
echo ""
echo "▶ [5/5] Estado final de los contenedores:"
docker compose ps

echo ""
echo "=================================================================="
echo "🎉 ¡DESPLIEGUE EN PRODUCCIÓN COMPLETADO CON ÉXITO!"
echo "=================================================================="
echo "  • Web Pública (HTTPS):  https://films.javiergalvez.com"
echo "  • Acceso / Login:       https://films.javiergalvez.com/login"
echo "  • Salud / Healthcheck:  https://films.javiergalvez.com/up"
echo "  • Host Local (Proxy):   http://127.0.0.1:${FILMMAKER_PORT}"
echo "=================================================================="
