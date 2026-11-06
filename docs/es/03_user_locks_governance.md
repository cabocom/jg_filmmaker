# 03. Gobernanza de Candados (`user_locks`)

[← 02. Pipeline Cinematográfico](02_pipeline_stages.md) | [04. Biblia Contextual de Proyecto →](04_context_bible.md)

---

## 1. Principio de No Invasión Creativa

En herramientas de asistencia con Inteligencia Artificial, uno de los fallos de experiencia de usuario más graves es la **sobrescritura destructiva**: cuando un autor pule manualmente una frase o fija un término específico y una re-ejecución del modelo borra sus cambios.

`jg_filmmaker` resuelve este problema mediante una arquitectura de candados y sustitución inmutable gestionada por [UserLockResolver](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/UserLockResolver.php).

---

## 2. Estructura JSONB del Campo `user_locks`

Cada bloque narrativo en `script_blocks` posee una columna `user_locks` tipada como JSONB (con cast a `array` en Eloquent) con la siguiente estructura canónica:

```json
{
  "locked_fields": [
    "structured_text",
    "screenplay_content"
  ],
  "manual_diffs": [
    {
      "field": "screenplay_content",
      "timestamp": "2026-10-09T21:40:00Z",
      "diff": "Ajuste manual de tono en la réplica de Javier"
    }
  ],
  "pinned_terms": {
    "Javi": "Javier",
    "Bar Pepe": "BAR CENTRAL",
    "callejon": "Callejón Oscuro"
  }
}
```

---

## 3. Comportamiento y Algoritmo de Resolución

El servicio [UserLockResolver](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/UserLockResolver.php) proporciona los métodos de control:

### 3.1. Protección de Campos Bloqueados (`isFieldLocked`)
```php
public function isFieldLocked(ScriptBlock $block, string $field): bool
{
    $locks = $block->user_locks ?? [];
    $lockedFields = $locks['locked_fields'] ?? [];
    return in_array($field, $lockedFields, true);
}
```
Si un campo está bloqueado (por ejemplo, `structured_text` tiene el candado activado), el pipeline aborta la sobreescritura automática por IA y emite un mensaje informativo al cineasta.

### 3.2. Preservación de Términos Fijados (`applyLocks`)
```php
public function applyLocks(ScriptBlock $block, string $content, string $field): string
{
    if ($this->isFieldLocked($block, $field)) {
        return $block->{$field} ?? $content;
    }

    $locks = $block->user_locks ?? [];
    $pinned = $locks['pinned_terms'] ?? [];

    foreach ($pinned as $alias => $canonical) {
        $content = str_ireplace($alias, $canonical, $content);
    }

    return $content;
}
```
Cualquier término fijado por el usuario se respeta como regla inmutable sobre el texto transformado por el agente cognitivo.

### 3.3. Control Manual desde la Interfaz
En el editor Livewire ([ScriptEditor](file:///Users/javiergalwire/ScriptEditor.php)), cada bloque muestra un badge de candado:
- Candado abierto: El campo se actualizará con la nueva sugerencia cognitiva.
- Candado cerrado (🔒): El contenido queda blindado contra regeneraciones globales.
