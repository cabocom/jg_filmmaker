# jg_filmmaker

**jg_filmmaker** es una aplicación web desacoplada en Laravel 12 concebida para la gestión integral y asistencia con Inteligencia Artificial del ciclo completo de producción cinematográfica (Idea -> Tratamiento -> Guión Literario -> Desglose Técnico de Planos -> Rodaje y Tomas de Vídeo -> Montaje y Pre-corte NLE).

Forma parte del ecosistema multi-servicio `javiergalvez-ia`, comunicándose de manera asíncrona mediante peticiones HTTP firmadas con **HMAC-SHA256** con el runtime cognitivo `jg_agente` a través del plugin especializado `jg_plugin_filmmaker`.

---

## Características Implementadas (Fases 1 a 6)

1. **Multitenancy y Claves UUIDv4 Anti-IDOR:**
   - Todas las entidades de dominio utilizan exclusivamente identificadores UUIDv4 (`$table->uuid('id')->primary()`).
   - Prevención estricta de ataques IDOR; no se exponen IDs numéricos secuenciales en rutas ni endpoints.
   - Todos los modelos Eloquent implementan el trait `Illuminate\Database\Eloquent\Concerns\HasUuids`.

2. **Pipeline Cinematográfico en 6 Etapas:**
   - **Bruto (Raw):** Ingesta libre y rápida de ideas y notas del autor.
   - **Tratamiento:** Estructuración dramática en presente continuo mediante `treatment_structuring`.
   - **Guión Literario:** Formato estándar Fountain / Courier con sluglines, personajes en mayúsculas y diálogos.
   - **Planos Técnicos:** Detección y desglose de planos de cámara (`--- shot N (tipo) ---`) sincronizados con la BD.
   - **Rodaje y Tomas:** Subida directa de vídeo por toma con validación, marcado ágil de `is_good_take` y metadatos técnicos en JSONB.
   - **Montaje NLE:** Línea de tiempo interactiva (`RoughCutTimeline`), reproductor de vídeo, exportación de corte (JSON) y ensamble continuo mediante FFmpeg (`RoughCutAssemblyService`).

3. **Mecanismo de Candados de Usuario (`user_locks`):**
   - Retención de ediciones manuales y fijación de términos canónicos (`pinned_terms`) para proteger el trabajo del autor ante re-ejecuciones de IA.

4. **Biblia de Proyecto en Drawer Lateral:**
   - Gestión ágil de Personajes y Localizaciones con atajo global `Cmd+B` / `Ctrl+B` y persistencia reactiva en caliente.

5. **Diseño Dual y Tema Blanco (Light Mode):**
   - Integración nativa con Tailwind CSS v4 con selector dinámico claro/oscuro (☀️/🌙) y persistencia en `localStorage`.

6. **Integración CI/CD y Calidad:**
   - 28 pruebas unitarias y de integración pasando al 100% con PHPUnit / Laravel Test suite (`php artisan test`).
   - Verificación de formato y sintaxis con Laravel Pint (`vendor/bin/pint --test`).
   - Integración en el script unificado del ecosistema `scripts/check.sh`.

---

## Ejecución Local y Pruebas

```bash
# 1. Instalar dependencias
composer install

# 2. Configurar variables de entorno
cp .env.example .env
php artisan key:generate

# 3. Ejecutar migraciones y sembrado de prueba
php artisan migrate:fresh --seed

# 4. Iniciar servidor de desarrollo
php artisan serve --port=8010

# 5. Ejecutar suite de pruebas completa (28 tests)
php artisan test

# 6. Validar estilo de código con Pint
vendor/bin/pint --test
```

---

## Documentación Técnica Exhaustiva

Para consultar las especificaciones completas de modelos, flujos, seguridad HMAC y deudas técnicas, consulta:
- [PROJECT_CONTEXT.md](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/PROJECT_CONTEXT.md)
- [docs/specs/](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/specs/)
- [docs/develop/](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/develop/)
