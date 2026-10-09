# Walkthrough — Tema Blanco/Claro y Corrección Heurística de Pipeline de Guión

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker` / Ecosistema `javiergalvez-ia`  
**Autor:** Antigravity (Ingeniero Senior Full-Stack)

---

## 1. Prompt Recibido

```text
quiero que le hagas un tema blanco como tiene el jg_hub y el jg_dashboard.

no está funcionando bien, fijate en la captura que le he metido dos parrafos nuevos al bruto y me ha hecho cosas raras en los otras dos estapas.

se está usando ya la ia?
```

---

## 2. Acciones Realizadas

### 2.1. Implementación del Tema Blanco (Light Mode)
- **Tailwind v4 CSS:** Se declaró `@custom-variant dark (&:where(.dark, .dark *));` en [app.css](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/css/app.css).
- **Layout Base:** Se modificó [app.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/layouts/app.blade.php) para inicializar el tema desde `localStorage` (`jg_theme`/`console_theme`) por defecto en claro y se añadió un botón toggle interactivo (☀️/🌙).
- **Vistas Adaptadas:**
  - [project-list.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/projects/project-list.blade.php)
  - [script-editor.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/script-editor.blade.php)
  - [rough-cut-timeline.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/rough-cut-timeline.blade.php)
  - [context-bible-drawer.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/context-bible-drawer.blade.php)

### 2.2. Corrección del Formateador de Guión
- En [ScriptPipelineService.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php): se añadió la carga de `$scene->location` y su envío al cliente formateador.
- En [AgentClientService.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/JgAgente/AgentClientService.php):
  - Inclusión del slugline canónico derivado de la escena (`INT. BAR CENTRAL - NOCHE 1`).
  - Eliminación de prefijos repetitivos (`En LOCALIZACION:`).
  - Corrección del regex de diálogo para evitar falsos positivos con frases narrativas como *"Javier mira a Juan con miedo..."*.

---

## 3. Verificación y Resultados

1. **PHPUnit / Artisan Test:**
   - `php artisan test`: 28 tests pasados, 144 aserciones (100% éxito).
2. **Estilo y Linter:**
   - `vendor/bin/pint --test`: Aprobado sin observaciones de estilo.
