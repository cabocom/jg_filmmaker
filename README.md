# jg_filmmaker

**jg_filmmaker** es una aplicación web desacoplada en Laravel 12 concebida para la gestión integral y asistencia con Inteligencia Artificial del ciclo completo de producción cinematográfica: desde la concepción dramática inicial hasta el montaje y pre-corte NLE:

$$\text{Idea / Bruto} \longrightarrow \text{Tratamiento} \longrightarrow \text{Guión Literario} \longrightarrow \text{Planos Técnicos} \longrightarrow \text{Rodaje / Tomas} \longrightarrow \text{Montaje / Pre-corte NLE}$$

Forma parte del ecosistema multi-servicio `javiergalvez-ia`, comunicándose de manera asíncrona mediante peticiones HTTP firmadas con **HMAC-SHA256** con el runtime cognitivo `jg_agente` a través del plugin especializado `jg_plugin_filmmaker`.

---

## Características Implementadas (Fases 1 a 17)

### 1. Ingesta, Importación y Renderizado en Lote (Fase 17)
- **Importación Inteligente de Guiones (PDF y TXT):** Ingesta automática mediante `ScriptImportService` con parseo heurístico de sluglines (`INT./EXT.`), tiempos de día, personajes en mayúsculas, acotaciones y bloques de diálogo o acción.
- **Ventana de Confirmación Destructiva:** Modal de previsualización que resume escenas, personajes y localizaciones detectadas, con requerimiento de confirmación explícita antes de sobreescribir la obra actual o crear un proyecto nuevo.
- **Edición Ágil de la Obra:** Modificación inmediata del título del proyecto desde el propio editor continuo del estudio o desde la vista de proyectos.
- **Renderizado y Procesamiento en Lote (Batch Processing):** Botón `⚡ Renderizar en Lote` para procesar por escena o proyecto completo brutos a tratamientos, tratamientos a guiones, guiones a planos técnicos o pipeline completo, respetando los candados de usuario (`user_locks`).

### 2. Experiencia de Escritura y Edición Cinematográfica (Estudio)
- **Modo Redacción StoryArchitect:** Acordeón vertical continuo donde todas las escenas se despliegan una tras otra para lectura fluida de guion de extremo a extremo sin saltar de pantalla.
- **Drag & Drop Físico y Reordenación Atómica:** Tiradores táctiles verticales (`⋮⋮`) alineados con la primera línea de texto. Al arrastrar, desplaza visualmente la fila completa (`setDragImage`) y reordena atómicamente (`DB::transaction`) tanto escenas como bloques.
- **Divisores Reactivos WordPress Gutenberg:** Espacios de inserción intermedia con línea horizontal ámbar continua visible en hover de lado a lado y botón central circular `+` para añadir texto bruto o crear escenas intermedias.
- **Pipeline Independiente por Escena:** Cada escena dispone de su propio selector de etapas (`Idea | Tratamiento | Guión | Planos / Tomas | Montaje`), permitiendo que el estado visual de una escena no altere a las demás.
- **Refinamiento Teatral Directo:** Motor de inferencia que detecta construcciones de habla indirecta y las transforma automáticamente en parlamentos directos canónicos en formato Fountain (`PERSONAJE` en mayúsculas, acotaciones y réplicas directas).
- **Drawer Lateral al 60% Garantizado:** Modal lateral derecho que permite consultar todas las etapas del bloque, editar texto, alternar tomas buenas y examinar la trazabilidad cognitiva sin expandirse a toda la pantalla.
- **Gobernanza de Candados (`user_locks`):** Protección estricta de modificaciones del autor y términos fijos (`pinned_terms`) ante re-ejecuciones de IA.
- **Biblia de Proyecto Contextual:** Gestión rápida de Personajes y Localizaciones con geometría centrada homogénea y soporte de atajo `Cmd+B` / `Ctrl+B`.
- **Montaje NLE y Ensamble FFmpeg:** Timeline reactivo de tomas aprobadas (`is_good_take`), subida y streaming de vídeo, y concatenación física continua con FFmpeg (`RoughCutAssemblyService`).

### 2. Autenticación Passwordless y Seguridad (Estilo jg_hub)
- **Acceso por Email con PIN OTP:** Códigos alfanuméricos efímeros (`XXXX-XXXX`) de un solo uso con ventana de validez de 15 minutos almacenados mediante hash SHA-256 en `one_time_passwords`.
- **Modo Especial Localhost / Desarrollo:** En entorno local o conexiones desde 127.0.0.1, la pantalla de verificación muestra un banner destacado con el código generado y un botón para autocompletar el campo al instante sin depender de SMTP.
- **Detección de Replay Attacks:** Bloqueo inmediato ante códigos expirados o reutilizados, registrando el incidente forense en `security_breach_logs` (IP, User Agent, motivo y fecha).

### 3. Panel de Administración y Gobernanza (`/admin`)
- **Protección por Roles:** Middleware `EnsureUserIsAdmin` protegiendo todas las rutas bajo `/admin/*` con código HTTP 403 estricto.
- **Gobernanza de Usuarios (`/admin/users`):** Conteo de proyectos frente a cuota contratada (`paid_project_slots`), volumen de tokens, coste acumulado y conmutador reactivo del rol `is_admin`.
- **Auditoría Cuantitativa con Privacidad Estricta (`/admin/users/{user}`):** Panel de inspección cuantitativa que agrega bloques por etapa sin cargar ni exponer en memoria ningún texto o argumento privado del autor.
- **Catálogo Global y Migración de Obras (`/admin/projects`):** Listado centralizado de todos los proyectos de la plataforma con buscador reactivo, filtros por titular y **modal de transferencia de obras**, permitiendo reasignar atómicamente la titularidad de cualquier proyecto a otro usuario.
- **Gestión de Tarifas de Tokens IA (`/admin/pricing`):** Configuración de costes por 1M de tokens para proveedores y modelos (Groq Llama 3.3 70B, Llama 3.1 8B, Gemini 1.5 Flash, Ollama) y selección de tarifa activa.
- **Visor Forense de Seguridad (`/admin/security`):** Monitor de incidentes y brechas por reutilización de tokens.

### 4. Telemetría de Costes IA
- **Registro Centralizado por Petición:** Persistencia en `agent_request_logs` con tokens de entrada, salida, tiempo de respuesta en milisegundos y cálculo dinámico de costes estimados en USD mediante `AgentTelemetryService`.

### 5. Multitenancy y Calidad
- **Claves UUIDv4 Anti-IDOR:** Todas las entidades (`User`, `Project`, `Scene`, `ScriptBlock`, `Shot`, `Take`) utilizan identificadores UUIDv4.
- **Diseño Dual Claro / Oscuro:** Integración nativa con Tailwind CSS v4, adaptable a tema blanco puro y tema oscuro con persistencia en `localStorage`.

---

## Ejecución Local y Pruebas

```bash
# 1. Instalar dependencias PHP y Node
composer install
npm install

# 2. Configurar variables de entorno
cp .env.example .env
php artisan key:generate

# 3. Ejecutar migraciones y sembrado de prueba
php artisan migrate --force
php artisan db:seed --force

# 4. Iniciar servidor de desarrollo
php artisan serve --port=8010

# 5. Ejecutar la suite completa de pruebas automatizadas (48 tests, 260 aserciones)
php artisan test

# 6. Validar estilo de código y estándares con Pint
./vendor/bin/pint --test
```

### Ejecución con Docker

```bash
# Levantar el servicio en el puerto 8010
docker compose up -d --build

# Ver logs del contenedor
docker compose logs -f jg_filmmaker
```

---

## Documentación Técnica Exhaustiva

Para consultar las especificaciones completas de modelos, flujos, seguridad HMAC y deudas técnicas:
- **[PROJECT_CONTEXT.md](PROJECT_CONTEXT.md)** — Contexto vivo del proyecto, decisiones arquitectónicas y deudas técnicas.
- **[docs/README.md](docs/README.md)** — Índice general de la documentación técnica.
  - [01. Arquitectura General del Sistema](docs/01_architecture.md)
  - [02. Pipeline Cinematográfico en 6 Etapas](docs/02_pipeline_stages.md)
  - [03. Gobernanza de Candados (`user_locks`)](docs/03_user_locks_governance.md)
  - [04. Biblia Contextual de Proyecto](docs/04_context_bible.md)
  - [05. Integración Inter-Servicio HMAC-SHA256](docs/05_hmac_agent_integration.md)
  - [06. Gestión de Medios y Ensamble FFmpeg](docs/06_media_takes_ffmpeg.md)
  - [07. Estrategia de Testing y CI/CD](docs/07_testing_quality_cicd.md)
  - [08. Autenticación Passwordless, Panel de Administración y Telemetría](docs/08_auth_admin_telemetry.md)
