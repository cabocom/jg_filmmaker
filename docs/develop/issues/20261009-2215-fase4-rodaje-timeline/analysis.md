# Análisis Técnico — Fase 4: Rodaje, Carga de Archivos de Vídeo por Toma y Timeline de Montaje

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker`  
**Autor:** Antigravity (Ingeniero Senior Laravel / Full-Stack)

---

## 1. Contexto y Objetivos

Habiendo validado la estructuración cognitiva y el desglose de planos mediante el pipeline canónico en la Fase 3B, la Fase 4 aborda la etapa de **Rodaje y Montaje (Editing Stage)** dentro de la aplicación cinematográfica `jg_filmmaker`.

### Objetivos Clave:
1. **Almacenamiento Seguro Particionado por UUID:**
   - Estructurar el almacenamiento en el disco público de Laravel bajo el esquema canónico:
     `media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.{ext}`
   - Garantizar la no exposición de IDs autoincrementales IDOR y prevenir colisiones entre tomas o proyectos.
   - Soportar tipos MIME de vídeo (`mp4`, `mov`, `webm`, `m4v`, `quicktime`) con límite de subida de hasta 100MB.
   - Implementar borrado automático del archivo físico en storage cuando una toma se elimina o cuando su vídeo se reemplaza.
2. **Rodaje en el Estudio Cinematográfico (`ScriptEditor`):**
   - Incorporar `WithFileUploads` de Livewire 3 para subida asíncrona de archivos multimedia.
   - Acciones reactivas por toma:
     * Toggle `is_good_take` (Toma Buena / Descarte).
     * Edición inline de notas de dirección y posproducción (`editorial_notes`).
     * Edición y persistencia de metadatos técnicos en JSONB (`duration_seconds`, `resolution`, `fps`, `codec`).
     * Subida y reemplazo de archivo de vídeo con feedback de progreso.
     * Eliminación de tomas con confirmación y limpieza física del disco.
3. **Visor de Montaje / Pre-corte de Edición (`RoughCutTimeline`):**
   - Nueva pestaña "🎬 Montaje" en el estudio.
   - Filtrado automático de tomas marcadas exclusivamente como `is_good_take = true`.
   - Ordenación secuencial cinematográfica por escena (`scene_order`), plano (`shot_number`) y toma (`take_number`).
   - Monitor central con reproductor HTML5 de vídeo y claqueta virtual en caso de clips sin media cargado.
   - Timeline visual inferior estilo NLE (Non-Linear Editor) con tarjetas de clips interactivos y badges diferenciados por tipo de plano (PG, PM, PP, PD).
   - Exportación de la lista de montaje en formato estructurado JSON / EDL.
4. **Garantía de Calidad:**
   - 100% de tests unitarios y de integración pasando en PHPUnit.
   - Cumplimiento de estándares de estilo con Laravel Pint.

---

## 2. Decisiones de Diseño y Arquitectura

### 2.1. Ciclo de Vida del Archivo y Modelo `Take`
En el modelo `App\Models\Take`:
- Se implementó en el hook `booted()` un listener `deleting` que verifica la existencia de `$take->file_path` en `Storage::disk('public')` y lo suprime físicamente.
- Se implementó el accesor `getVideoUrlAttribute()` que resuelve `Storage::disk('public')->url($this->file_path)` para consumo inmediato por etiquetas `<video>` HTML5 o reproductores externos.
- Se añadió el método `generateStoragePath(string $extension = 'mp4')` que calcula de forma determinista la ruta particionada por UUID.

### 2.2. Componente Modular `RoughCutTimeline`
Se diseñó como un componente Livewire autónomo (`App\Livewire\Studio\RoughCutTimeline`) para permitir:
1. Reutilización como subvista reactiva en el `ScriptEditor` mediante la pestaña `'timeline'`.
2. Posible invocación como ruta de edición independiente o visor compartido.
3. Testeo aislado de filtros de escenas y de la exportación de archivos descargables (`exportRoughCutJson`).

### 2.3. Estructura de Exportación de Montaje (JSON/EDL)
El payload exportado contiene:
- Datos del proyecto (`id`, `title`).
- Timestamp de exportación y número total de cortes aprobados.
- Duración total estimada (suma de `technical_meta.duration_seconds`).
- Secuencia ordenada (`timeline`) con índices secuenciales, datos de escena, slugline, tipo de plano, ruta de fichero, URL web y notas editoriales.

---

## 3. Matriz de Componentes Creados y Modificados

| Componente | Rol / Ubicación | Propósito |
|---|---|---|
| `Take.php` | `app/Models/` | Añadido accesor `video_url`, generador de ruta UUID y hook de borrado físico. |
| `ScriptEditor.php` | `app/Livewire/Studio/` | Añadido trait `WithFileUploads`, métodos `startUploadVideo`, `uploadTakeVideo`, `deleteTake`, notas inline y pestaña `'timeline'`. |
| `script-editor.blade.php` | `resources/views/livewire/studio/` | Pestaña de navegación '🎬 Montaje', controles de subida de vídeo por toma, edición de notas y botón de eliminación. |
| `RoughCutTimeline.php` | `app/Livewire/Studio/` | Componente Livewire para corte previo, filtrado de tomas buenas, cálculo de tiempos y exportación JSON. |
| `rough-cut-timeline.blade.php` | `resources/views/livewire/studio/` | Interfaz visual NLE con reproductor HTML5, claqueta mockup y timeline horizontal. |
| `TakeMediaUploadTest.php` | `tests/Feature/` | Pruebas de subida simulada de vídeo, ruta UUID y borrado físico al eliminar tomas. |
| `RoughCutTimelineTest.php` | `tests/Feature/` | Pruebas de filtrado estricto de tomas buenas, filtros por escena y exportación JSON. |
