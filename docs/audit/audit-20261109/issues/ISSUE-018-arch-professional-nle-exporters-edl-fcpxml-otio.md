# [ISSUE-018] Exportadores Profesionales NLE Estándar de la Industria (CMX 3600 EDL, Final Cut Pro FCPXML y DaVinci Resolve)

- **Severidad:** Idea de Mejora
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/RoughCutAssemblyService.php:148-206`, `jg_filmmaker/app/Livewire/Studio/RoughCutTimeline.php:111-125`
- **Categoría:** Arquitectura

## 1. Descripción
Actualmente, `RoughCutAssemblyService::generateEdlManifest()` exporta un archivo `.json` propietario con la estructura interna de la base de datos (`version: "1.0"`, `clips: [...]`). Aunque resulta útil para depuración técnica, ninguna suite profesional de posproducción cinematográfica (DaVinci Resolve, Apple Final Cut Pro, Adobe Premiere Pro, Avid Media Composer) puede importar directamente ese esquema JSON propietario.

Los directores, montadores y coloristas que utilizan `jg_filmmaker` para el desglose, rodaje y selección de *tomas buenas* (`is_good_take`) necesitan trasladar su línea de tiempo preliminar (*Rough Cut*), junto con las notas de dirección (`editorial_notes`), nombres de escena y tipos de plano, directamente a su NLE sin tener que reconstruir el montaje clip a clip manualmente.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/RoughCutAssemblyService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/RoughCutAssemblyService.php#L151-L185):
```php
    public function generateEdlManifest(Project $project): array
    {
        $goodTakes = $this->getOrderedGoodTakes($project);
        $timeline = [];
        $currentOffset = 0.0;

        foreach ($goodTakes as $index => $take) {
            $duration = (float) ($take->technical_meta['duration_seconds'] ?? 0.0);
            $timeline[] = [
                'order' => $index + 1,
                'scene_order' => $take->shot->scene->scene_order,
                'slugline' => $take->shot->scene->slugline,
                // ...
```

## 3. Impacto Operativo o Riesgo
- **Fricción en el Handoff a Posproducción:** Limita el valor de la etapa 5 (`editing`) a una previsualización web cerrada, desaprovechando los metadatos ricos de tiempo (`duration_seconds`, `fps`, `resolution`), escena, plano y notas editoriales ya capturados en `jg_filmmaker`.

## 4. Solución o Mejora Recomendada
Incorporar un servicio `NleTimelineExportService` capaz de exportar la secuencia de tomas buenas en dos formatos universales de intercambio cinematográfico:

1. **CMX 3600 EDL (`.edl`):** Compatible con el 100% de sistemas de edición y etalonaje (DaVinci Resolve, Premiere, Avid), convirtiendo segundos flotantes a código de tiempo SMPTE `HH:MM:SS:FF` según el framerate del proyecto (24/25 fps) e inyectando el nombre del archivo fuente (`* FROM CLIP NAME:`) y las notas editoriales (`* COMMENT:`):
   ```text
   TITLE: PROYECTO_ROUGH_CUT
   FCM: NON-DROP FRAME

   001  AX       AA/V  C        00:00:00:00 00:00:04:12 01:00:00:00 01:00:04:12
   * FROM CLIP NAME: SC01_SH01_TK02.mp4
   * COMMENT: [ESCENA 1 - INT. CAFETERIA - PG] Cortar antes del carraspeo
   ```
2. **Final Cut Pro XML v1.10 (`.fcpxml`):** Compatible de forma nativa con **Final Cut Pro** y **DaVinci Resolve**, declarando los `<asset>` con sus rutas relativas de medios y construyendo el `<spine>` con marcadores (`<marker>`) para cada `editorial_note` y metadatos de escena/plano.

## 5. Resolución y Verificación Técnica
- **Servicio Dedicado de Exportación NLE (`NleTimelineExportService`):** Implementada la exportación profesional respetando SOLID (< 300 LOC) con soporte nativo de:
  - **CMX 3600 EDL (`.edl`):** Conversión precisa de duraciones flotantes a código de tiempo SMPTE `HH:MM:SS:FF`, bloque de encabezado estándar (`TITLE`, `FCM: NON-DROP FRAME`), código de programa inicial a 1 hora (`01:00:00:00`), enlace al nombre del clip y comentarios con número de escena, slugline, tipo de plano y notas editoriales.
  - **Final Cut Pro XML v1.10 (`.fcpxml`):** Estructura XML completa con formato de proyecto 1080p24, recursos `<asset>`, secuencia `<spine>` de clips con offsets de tiempo exactos y marcadores `<marker>` embebidos para cada anotación del director.
- **Integración Reactiva en `RoughCutTimeline`:** Nuevos métodos de descarga `exportCmx3600Edl()` y `exportFcpxml()` con botones específicos en la barra superior de la mesa de montaje.
- **Validación Automatizada:** Suite `NleTimelineExportTest` validando el cálculo SMPTE, sintaxis EDL, formato FCPXML y descarga desde el componente Livewire. 306 tests PHPUnit pasando al 100% (1.426 assertions).

