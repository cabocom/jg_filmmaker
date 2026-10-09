#!/usr/bin/env bash
set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "========================================"
echo "🔍 Verificando jg_filmmaker..."
echo "========================================"

echo ""
echo "1️⃣  [Pint] Linter y formato de código PHP..."
./vendor/bin/pint --test

echo ""
echo "2️⃣  [Artisan/PHPUnit] Suite de pruebas automatizadas..."
php artisan test

echo ""
echo "========================================"
echo "✅ ¡Todas las comprobaciones de jg_filmmaker pasaron con éxito!"
echo "========================================"
