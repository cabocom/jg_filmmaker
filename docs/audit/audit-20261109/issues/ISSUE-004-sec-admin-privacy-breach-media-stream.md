# [ISSUE-004] Violación de Privacidad en Administración: `MediaStreamController` Permite a Admins Reproducir Vídeos Privados

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Http/Controllers/MediaStreamController.php:21-56` vs `jg_filmmaker/app/Policies/ProjectPolicy.php:10-21`
- **Categoría:** Seguridad

## 1. Descripción
La arquitectura de seguridad y privacidad de `jg_filmmaker` establece que los administradores (`is_admin = true`) pueden supervisar métricas operativas, consumo de tokens y metadatos estructurales en `/admin`, pero **bajo ninguna circunstancia** tienen permiso para visualizar el contenido creativo privado de otros usuarios (guiones, escenas, bloques o material audiovisual).

Esta regla está implementada correctamente en `ProjectPolicy::view()`, donde se exige estrictamente `$project->user_id === $user->id` sin excepción para administradores. Sin embargo, `MediaStreamController::streamTake()` y `MediaStreamController::streamRender()` implementan una comprobación manual ad-hoc (`if ($project->user_id !== $user->id && ! $user->is_admin)`) que **exime a los administradores**, permitiéndoles descargar y reproducir cualquier toma de rodaje (`Take`) o montaje final (`Render`) de cualquier usuario.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Policies/ProjectPolicy.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Policies/ProjectPolicy.php#L10-L21):
```php
    /**
     * Determine whether the user can view the model.
     * Strict privacy: Admins CANNOT view other users' creative content.
     */
    public function view(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
```

Contradicho en [`jg_filmmaker/app/Http/Controllers/MediaStreamController.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Http/Controllers/MediaStreamController.php#L25-L53):
```php
        $user = $request->user();
        if (! $user || ($project->user_id !== $user->id && ! $user->is_admin)) {
            abort(403, 'No tienes permiso para reproducir esta toma.');
        }
        // ...
        $user = $request->user();
        if (! $user || ($project->user_id !== $user->id && ! $user->is_admin)) {
            abort(403, 'No tienes permiso para acceder a este montaje.');
        }
```

## 3. Impacto Operativo o Riesgo
- **Quebrantamiento del Compromiso de Privacidad Creativa:** Un usuario con rol de administrador puede acceder a `/projects/{project}/takes/{take}/stream` o `/projects/{project}/renders/{filename}` de cualquier cineasta y visualizar o descargar su material de rodaje inédito sin dejar rastro en `AdminAuditLog`.

## 4. Solución o Mejora Recomendada
Delegar la autorización en `ProjectPolicy` mediante `$this->authorize('view', $project)` (o verificar estrictamente `$project->user_id !== $user->id` sin el bypass `! $user->is_admin`), manteniendo coherencia absoluta con `AudioStreamController` y `ScriptExportController`:

```php
        $user = $request->user();
        if (! $user || $project->user_id !== $user->id) {
            abort(403, 'No tienes permiso para reproducir esta toma.');
        }
```

