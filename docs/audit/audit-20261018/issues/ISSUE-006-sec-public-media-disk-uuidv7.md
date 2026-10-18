# [ISSUE-006] Vídeos de tomas en disco `public` sin autenticación y UUID realmente v7 (no v4 como documenta la spec)

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `config/filesystems.php:41-48`, `app/Models/Take.php:68-75`, `app/Services/RoughCutAssemblyService.php:36,61,117`, todos los modelos con `HasUuids`
- **Categoría:** Seguridad

## 1. Descripción del Problema
1. **Medios públicos:** tomas y renders se guardan en `Storage::disk('public')` y se exponen como `APP_URL/storage/media/projects/{project}/scenes/{scene}/takes/{take}.mp4`. Cualquiera con la URL (sin sesión) descarga el vídeo inédito. La única "protección" es la opacidad del UUID, que no es un control de acceso (URLs en logs de nginx/Referer, historial, capturas, compartición accidental).
2. **UUID v7, no v4:** en Laravel 12 `HasUuids` genera `Str::uuid7()` (confirmado en `vendor/.../Concerns/HasUuids.php:18`). Los UUIDv7 contienen un timestamp de 48 bits y son **ordenados y parcialmente predecibles**, lo que contradice `docs/specs/01_architecture_and_models.md` y `docs/01_architecture.md` ("UUIDv4 estricto") y debilita la defensa anti-enumeración en la que se apoya todo el modelo. La spec exige `HasUuids` y a la vez v4: ambas afirmaciones no son compatibles en Laravel 12 (hay que usar `HasVersion4Uuids`).

## 2. Evidencia de Código
```php
// filesystems.php
'public' => ['driver' => 'local', 'root' => storage_path('app/public'),
             'url' => rtrim(env('APP_URL'), '/').'/storage', 'visibility' => 'public'],
// Take.php
return Storage::disk('public')->url($this->file_path);
```
```php
// vendor/laravel/framework/.../HasUuids.php
return (string) Str::uuid7();
```

## 3. Impacto Operativo o de Seguridad
Fuga de material audiovisual inédito (propiedad intelectual), eludiendo cualquier control de propiedad. Predictibilidad parcial de identificadores que agrava ISSUE-003/004.

## 4. Solución Recomendada
- Disco privado `local` (`storage/app/private/media`) y descarga/stream mediante ruta autenticada o URL temporal firmada con control de propiedad:
```php
Route::get('/media/takes/{take}', fn (Take $take) => Storage::disk('local')->response($take->file_path))
     ->middleware(['auth','signed'])->name('media.take');
```
- Cambiar los modelos a `Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids` (o actualizar la documentación a v7 si se acepta el trade-off, y asumir que el aislamiento depende exclusivamente de las policies).
- Añadir test de arquitectura que falle si un modelo no usa v4.
