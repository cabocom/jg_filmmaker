# 04. Biblia Contextual de Proyecto (`jg_filmmaker`)

[← 03. Gobernanza de Candados](03_user_locks_governance.md) | [05. Integración Inter-Servicio HMAC →](05_hmac_agent_integration.md)

---

## 1. Visión y Propósito de la Biblia de Proyecto

La coherencia de una obra cinematográfica depende de mantener la fidelidad psicológica de los personajes y las características físicas de los escenarios a lo largo de todo el arco dramático.

En `jg_filmmaker`, la Biblia de Proyecto almacena la información canónica de:
1. **Personajes (`Character`):** Arquetipos, psicología, vestuario y rasgos particulares.
2. **Localizaciones (`Location`):** Tipo (`INT`, `EXT`, `INT/EXT`), notas de arte, diseño de producción e iluminación.

---

## 2. Drawer Lateral Interactivo (`ContextBibleDrawer`)

Para no forzar al cineasta a abandonar el editor de guión ni romper su flujo de concentración:
- **Componente:** [ContextBibleDrawer](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php).
- **Activación por Atajo Global:** Se abre y cierra instantáneamente presionando `Cmd+B` (en macOS) o `Ctrl+B` (en Linux/Windows), o pulsando el botón *"📖 Biblia de Proyecto"* en la cabecera.
- **Pestañas Reactivas:**
  - Pestaña de Personajes: Listado, creación rápida con formulario inline y edición de psicología/vestuario.
  - Pestaña de Localizaciones: Listado, creación rápida y configuración de tipo (`INT`/`EXT`).
- **Persistencia en Caliente:** Al guardar un nuevo personaje o localización desde el cajón lateral, la base de datos se actualiza de inmediato mediante Livewire y queda instantáneamente disponible para los algoritmos de formateo de guión.

---

## 3. Inyección de Contexto en el Pipeline

Cuando se dispara la transición de un bloque narrativo:
1. `ScriptPipelineService` extrae la lista de personajes del proyecto (`$project->characters->pluck('name')->toArray()`).
2. Resuelve la localización asignada a la escena actual (`$scene->location->name` y `$scene->location->type`).
3. Envía esta información en la carga útil HTTP (`characters_context` y `location_context`) a `jg_plugin_filmmaker`.
4. El formateador utiliza estos nombres exactos para:
   - Construir el slugline canónico (`INT. BAR CENTRAL - NOCHE 1`).
   - Detectar diálogos y sangrar con los nombres en mayúsculas centradas (`JAVIER`).
