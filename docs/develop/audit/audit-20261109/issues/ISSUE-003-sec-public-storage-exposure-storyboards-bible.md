# [ISSUE-003] Exposición Pública sin Autenticación de Viñetas de Storyboard e Imágenes de la Biblia en Disco `public`

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/StoryboardService.php:210-211`, `jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php:178, 235, 274, 324`, `jg_filmmaker/app/Models/Shot.php:44-51`, `jg_filmmaker/app/Models/Character.php:54-61`, `jg_filmmaker/app/Models/Location.php:43-50`
- **Categoría:** Seguridad

## 1. Descripción
Aunque las tomas de vídeo (`takes`) y los montajes (`renders`) fueron migrados al disco privado (`local`) y protegidos tras `MediaStreamController`, todos los activos gráficos del proyecto siguen almacenándose en el disco público (`Storage::disk('public')`) y sirviéndose directamente a través del symlink `public/storage` mediante `asset('storage/' . $path)` sin pasar por ningún middleware de autenticación ni control de acceso por propietario:
1. **Viñetas de Storyboard:** `projects/{project_id}/storyboards/scene_{scene_id}/shot_{shot_id}_{timestamp}.{ext}`
2. **Referencias de Personajes:** `projects/{project_id}/characters/{hash}.{ext}`
3. **Referencias de Localizaciones:** `projects/{project_id}/locations/{hash}.{ext}`
4. **Atrezzo de Planos (Props):** `projects/{project_id}/props/shot_{shot_id}/...`

Además, en `ContextBibleDrawer` se admiten archivos `.heic` y `.heif` en la validación backend (`mimes:jpeg,jpg,png,webp,heic,heif`) por si falla o se omite la conversión cliente con `heic2any`. Si un archivo `.heic` llega al servidor:
- Se guarda con extensión `.heic` y los navegadores no-Safari son incapaces de renderizarlo en etiquetas `<img>`.
- Cuando `StoryboardService::getStorageImageData()` lee esa imagen para enviarla a `jg_plugin_filmmaker/storyboard.py`, en `storyboard.py:189-201` cualquier `mime_type` distinto de `image/jpeg` se etiqueta forzosamente como `"image/png"`, enviando bytes HEIC bajo una cabecera MIME `image/png` inválida a la API de Gemini.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/StoryboardService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/StoryboardService.php#L209-L212):
```php
        $relativePath = "projects/{$projectId}/storyboards/scene_{$sceneId}/shot_{$shot->id}_{$timestamp}.{$ext}";
        Storage::disk('public')->put($relativePath, $binaryImage);
```

En [`jg_filmmaker/app/Models/Shot.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Shot.php#L44-L51):
```php
    public function getStoryboardImageUrlAttribute(): ?string
    {
        if (! $this->storyboard_image_path) {
            return null;
        }

        return asset('storage/'.$this->storyboard_image_path);
    }
```

Y en [`jg_plugin_filmmaker/jg_plugin_filmmaker/storyboard.py`](file:///Users/javiergalvez/Documents/Code/javiergalvezvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/storyboard.py#L186-L194):
```python
    if character_reference_base64:
        parts.append(
            {
                "inline_data": {
                    "mime_type": "image/png"
                    if "jpeg" not in character_reference_mime.lower()
                    else "image/jpeg",
                    "data": character_reference_base64,
                }
            }
        )
```

## 3. Impacto Operativo o Riesgo
- **Acceso Directo sin Autenticación:** Cualquier URL de storyboard o fotografía de casting/localización compartida, almacenada en cachés de proxies o historial del navegador es accesible públicamente en Internet sin sesión activa y sin posibilidad de revocación.
- **Inconsistencia de Almacenamiento en Producción:** En el servidor de producción, donde `/var/www/html/storage/app` tiene montajes específicos, almacenar activos de proyectos en `storage/app/public/projects/...` fuera del prefijo `media/projects/...` también provoca que `Project::deleted` no los elimine al borrar un proyecto.

## 4. Solución o Mejora Recomendada
1. Migrar el almacenamiento de storyboards, personajes, localizaciones y props al disco privado (`Storage::disk('local')` bajo `media/projects/{project_id}/...`) y servirlos mediante un endpoint autenticado en `MediaStreamController` con validación estricta de `ProjectPolicy` y cabeceras `Cache-Control: private, max-age=3600`.
2. En `storyboard.py`, respetar el `mime_type` validado en una lista blanca (`image/png`, `image/jpeg`, `image/webp`) en lugar de forzar `image/png` para cualquier formato no-JPEG.

## 5. Resolución Aplicada (2026-11-14)
- **Migración a Disco Local y Rutas Autenticadas:**
  - `MediaStreamController.php`: Se añadieron los métodos `streamStoryboard(Shot $shot)`, `streamCharacter(Character $character)` y `streamLocation(Location $location)`. Validan tenencia por `user_id` o administrador y sirven el contenido con cabeceras `Cache-Control: private, max-age=3600`. Soporta fallback a `public` para activos legados sin interrumpir instalaciones existentes.
  - `routes/web.php`: Rutas protegidas bajo middleware `auth`: `media.storyboard`, `media.character`, `media.location`.
  - `Shot.php`, `Character.php`, `Location.php`: Actualizados los accessors `storyboard_url`, `reference_image_url` para generar URLs de ruta protegida en vez de `asset('storage/...')`.
  - `StoryboardService.php`, `ManageBibleCharacterAction.php`, `ManageBibleLocationAction.php`: Almacenamiento directo en `Storage::disk('local')` bajo la estructura unificada `media/projects/{projectId}/...`.
  - `StoryboardImageProcessor.php`: Comprueba el disco `local` preferentemente y `public` como retrocompatibilidad.
  - `WithBibleEntities.php`: Restringida la validación a formatos universalmente compatibles con navegadores y la API de Gemini (`jpeg,jpg,png,webp`), delegando la conversión de HEIC al frontend (`heic2any`).
- **Detección Dinámica de MIME en Plugin:**
  - `jg_plugin_filmmaker/storyboard.py`: Se implementó la función `detect_image_mime()` basada en encabezados y magic bytes (`PNG`, `JPEG`, `WebP`) en lugar de asumir `image/png` o `image/jpeg` rígidamente.
- **Validación:**
  - Creada suite `tests/Feature/MediaStreamSecurityTest.php` con 5 pruebas cubriendo accesos no autenticados (302/401), multitenant no autorizado (403), permisos de administrador (200), cabeceras de caché privadas y fallback de almacenamiento legado.
  - Suite de `jg_filmmaker` (252 tests) y `jg_plugin_filmmaker` (64 tests) al 100%.

