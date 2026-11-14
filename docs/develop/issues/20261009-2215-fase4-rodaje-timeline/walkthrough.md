# Walkthrough — Fase 4: Rodaje, Carga de Archivos de Vídeo por Toma y Timeline de Montaje

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker`  
**Estado:** Completado y Verificado al 100%

---

## 1. Prompt Recibido

```text
[PROJECT: jg_filmmaker] - Fase 4: Rodaje, Carga de Archivos de Vídeo por Toma y Timeline de Montaje

Actúa como Ingeniero Senior Laravel/Full-Stack en el ecosistema jg_ia.

Hemos completado con éxito la Fase 3B: la estructuración, guionización y desglose técnico de planos operan de forma reactiva y probada al 100%.

Ahora abordaremos la Fase 4: Rodaje y Montaje, cubriendo la carga de archivos de vídeo para cada toma, el almacenamiento seguro por UUID, las observaciones editoriales y un visor interactivo de corte preliminar (Timeline / Rough Cut).

### CONTEXTO Y ESPECIFICACIONES:
- docs/specs/01_architecture_and_models.md
- docs/specs/04_shots_takes_media_management.md
- Modelos existentes: `Shot` y `Take` con casts para `technical_meta` (JSONB) y campos `is_good_take`, `file_path`, `editorial_notes`.

### DIRECTRICES DE IMPLEMENTACIÓN:
1. Almacenamiento y Carga de Archivos de Vídeo:
   - Configurar soporte de subida de archivos en Livewire (`WithFileUploads`).
   - El almacenamiento físico debe estructurarse mediante UUIDs en el disco local/público configurado:
     `media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.mp4`
   - Validación de archivos: admitir formatos de vídeo (`mp4`, `mov`, `webm`) hasta un límite configurable (ej. 100MB para desarrollo local).
   - Generación de enlace seguro/público para reproducción web inmediata.

2. Componente de Rodaje en el Estudio (`ScriptEditor` o componente hijo `TakeManager`):
   - En cada plano técnico (`Shot`), permitir:
     * Crear una nueva toma (`Take`) con número autoincremental relativo al plano.
     * Subir el archivo de vídeo asociado a esa toma con barra de progreso de subida.
     * Marcar/desmarcar "TOMA BUENA" (`is_good_take`) de manera reactiva.
     * Editar inline el campo `editorial_notes` (anotaciones de dirección y posproducción).
     * Registrar o editar los metadatos técnicos en `technical_meta` (duración en segundos, resolución, fps, codec).

3. Visor de Montaje / Pre-corte de Edición (`RoughCutTimeline` - Livewire):
   - Añadir una nueva pestaña o subvista en el estudio cinematográfico: "🎬 Montaje / Corte Previo".
   - Funcionalidad:
     * Filtrar exclusivamente las tomas marcadas como `is_good_take = true`, ordenadas según el orden de la escena y el número de plano.
     * Reproductor de vídeo HTML5 sincronizado que cargue y encadene las tomas válidas.
     * Timeline visual inferior estilo editor no lineal: bloques secuenciales con el tipo de plano (PG, PM, PP), duración y miniatura o badge de estado.
     * Botón de exportación: "Exportar Lista de Montaje" que devuelva un payload JSON o EDL estructurado con la lista secuencial de planos, rutas de archivo y notas editoriales.

4. Suite de Pruebas Automatizadas:
   - Crear `tests/Feature/TakeMediaUploadTest.php`:
     * Verificar la subida de un archivo de vídeo simulado con `UploadedFile::fake()->create('take1.mp4', 5000, 'video/mp4')`.
     * Validar que el archivo se almacena en la ruta particionada por UUID.
     * Validar que la eliminación de una toma borra su archivo físico del almacenamiento si existe.
   - Crear `tests/Feature/RoughCutTimelineTest.php`:
     * Validar que el visor de montaje solo lista tomas donde `is_good_take == true`.
     * Validar la exportación del JSON de corte preliminar.

### OBJETIVO DE ESTE PASO:
1. Implementa la carga y gestión de archivos de vídeo para `Take`.
2. Añade la interfaz de rodaje y la vista/pestaña de montaje con reproductor y timeline en `ScriptEditor`.
3. Ejecuta `php artisan test` y `vendor/bin/pint` para validar que todo el suite de pruebas y estilo de código quedan al 100%.
```

---

## 2. Acciones Realizadas

### 2.1. Almacenamiento Particionado y Modelo `Take`
- Actualizado [app/Models/Take.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Take.php):
  - Enlace simbólico de storage configurado con `php artisan storage:link`.
  - Método `generateStoragePath(string $extension)` que produce la ruta canónica `media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.{extension}`.
  - Accesor `video_url` (`getVideoUrlAttribute()`) para resolver la URL accesible en el navegador.
  - Listener `deleting` en el método `booted()` para eliminar automáticamente el archivo físico del disco `public` al suprimir una toma.

### 2.2. Gestión de Rodaje en `ScriptEditor`
- Actualizado [app/Livewire/Studio/ScriptEditor.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php):
  - Integrado el trait `WithFileUploads` de Livewire 3.
  - Métodos `startUploadVideo` y `uploadTakeVideo` con validación estricta de extensiones (`mp4`, `mov`, `webm`, `m4v`, `quicktime`) y hasta 100MB de tamaño.
  - Borrado automático del archivo físico previo si se sustituye el vídeo de una toma existente.
  - Métodos `startEditTakeNotes` y `saveTakeNotes` para edición rápida de anotaciones de dirección y posproducción.
  - Método `deleteTake` para supresión limpia de tomas.
  - Añadido el modo de vista `'timeline'` a la navegación de pestañas del estudio.
- Actualizada vista [resources/views/livewire/studio/script-editor.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/script-editor.blade.php):
  - Botón de pestaña `🎬 Montaje` en el encabezado.
  - En cada toma: formulario interactivo de subida de vídeo con feedback de carga, visualizador de enlace multimedia, edición rápida de notas y botón de eliminación.

### 2.3. Componente de Montaje y Línea de Tiempo (`RoughCutTimeline`)
- Creado [app/Livewire/Studio/RoughCutTimeline.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/RoughCutTimeline.php):
  - Consulta optimizada `getGoodTakes()` que filtra tomas aprobadas (`is_good_take = true`) ordenadas por escena, plano y número de toma.
  - Soporte de filtro por escena específica o vista global de todo el proyecto.
  - Método `exportRoughCutJson()` para descarga directa de la lista de montaje en JSON/EDL formateado.
- Creada vista [resources/views/livewire/studio/rough-cut-timeline.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/rough-cut-timeline.blade.php):
  - Monitor central con reproductor de vídeo HTML5 y claqueta cinematográfica si aún no hay vídeo cargado.
  - Panel lateral de especificaciones del corte activo (tipo de plano, notas de dirección, metadatos técnicos).
  - Pista inferior horizontal de clips estilo NLE (Non-Linear Editor) con badges de color por tipo de plano (PG, PM, PP, PD), tiempo en segundos y selector de clip activo.

---

## 3. Pruebas Automatizadas y Calidad

### Pruebas Creadas:
1. **[tests/Feature/TakeMediaUploadTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/TakeMediaUploadTest.php)**:
   - `test_take_video_can_be_uploaded_and_stored_in_uuid_partitioned_path`: Valida la subida de archivo fake y persistencia en ruta UUID.
   - `test_deleting_take_removes_physical_video_file_from_storage`: Valida la eliminación del archivo físico del almacenamiento al borrar la toma.
   - `test_replacing_video_deletes_previous_physical_file`: Valida que al sustituir un vídeo se elimina el fichero anterior.
2. **[tests/Feature/RoughCutTimelineTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/RoughCutTimelineTest.php)**:
   - `test_rough_cut_timeline_only_includes_good_takes_ordered_chronologically`: Valida que solo se listan tomas buenas y en estricto orden cronológico.
   - `test_rough_cut_timeline_filters_by_scene_when_selected`: Valida el selector reactivo de filtro por escena.
   - `test_rough_cut_exports_structured_json_list`: Valida la exportación y el esquema del JSON de montaje descargado.

### Resultados de Ejecución
```text
$ php artisan test
   PASS  Tests\Feature\AgentClientHmacSignatureTest (3 tests)
   PASS  Tests\Feature\DomainModelArchitectureTest (6 tests)
   PASS  Tests\Feature\ExampleTest (1 test)
   PASS  Tests\Feature\LivewireProjectDashboardTest (2 tests)
   PASS  Tests\Feature\LivewireScriptEditorTest (2 tests)
   PASS  Tests\Feature\RoughCutTimelineTest (3 tests)
   PASS  Tests\Feature\ScriptPipelineIntegrationTest (5 tests)
   PASS  Tests\Feature\TakeMediaUploadTest (3 tests)

  Tests:    25 passed (135 assertions)
  Duration: 0.45s
```

### Formateador de Código (Pint)
```text
$ ./vendor/bin/pint
  PASS  0 errors remaining.
```

---

## 4. Instrucciones de Uso y Verificación

1. Acceder al estudio en `http://127.0.0.1:8010/studio/{projectId}`.
2. En la pestaña **Planos / Tomas**:
   - Cada plano permite registrar nuevas tomas con numeración autoincremental.
   - En cada toma se puede subir o reemplazar un archivo de vídeo MP4/MOV.
   - El botón **✓ TOMA BUENA** alterna reactivamente entre aprobación y descarte.
   - El botón **✏️** permite modificar las notas de dirección y montaje al vuelo.
3. En la pestaña **🎬 Montaje**:
   - Se visualiza el monitor central y el timeline NLE inferior con todas las tomas buenas encadenadas.
   - Al pulsar en cualquier clip de la pista, el reproductor carga el vídeo correspondiente.
   - Al pulsar **⬇️ Exportar EDL / JSON**, se descarga instantáneamente la lista de montaje en formato JSON estructurado.
