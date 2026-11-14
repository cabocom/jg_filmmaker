# Walkthrough: [ISSUE-012] Sincronización del contrato de la Biblia dramática (`bio`, `psychology`, `costume_notes`) y prevención estricta de atributos inexistentes

- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Estado:** Implementado y Validado

---

## 1. Resumen de Cambios

Se ha unificado y blindado el contrato de transferencia de la Biblia dramática del proyecto entre la aplicación Laravel y el plugin cognitivo Python:

1. **Métodos de Serialización Canónicos `toAgentContext()`:**
   - En `Character.php`: se implementó `toAgentContext()` serializando `name`, `bio`, `psychology`, `costume_notes` y `custom_attributes`.
   - En `Location.php`: se implementó `toAgentContext()` serializando `name`, `type`, `art_department_notes`, `lighting_notes` y `address_reference`.

2. **Sustitución de Claves Inexistentes en Laravel:**
   - En `ScriptPipelineService`: se eliminaron los accesos a los atributos espurios `$c->role` y `$c->personality_notes` en `structureBlock()`, `formatBlockToScreenplay()` y `syncProjectCharacters()`. Ahora se invoca directamente `$c->toAgentContext()`.
   - En `ScriptEditor`: en `analyzePlotHoles()`, se eliminaron los accesos a `$c->role`, `$c->description` y `$l->description`, sustituyéndolos por las llamadas estandarizadas a `toAgentContext()`.
   - En `UserFactory`: se añadió `'is_admin' => false` por defecto.

3. **Prevención Estricta de Acceso a Atributos Faltantes:**
   - En `AppServiceProvider::boot()`: se activó `Model::preventAccessingMissingAttributes(! $this->app->isProduction())`, asegurando que cualquier intento en desarrollo o tests de leer atributos no definidos o no seleccionados arroje de inmediato una excepción `MissingAttributeException`.

4. **Enriquecimiento del Contexto en el Plugin Python (`jg_plugin_filmmaker`):**
   - En `structuring.py`: se actualizó `structure_treatment()` para formatear exhaustivamente los atributos reales `bio`, `psychology` y `costume_notes` de cada personaje, así como las notas de arte e iluminación de la localización, logrando que el LLM reciba toda la riqueza contextual definida por el cineasta.

---

## 2. Modificaciones en el Código

### `app/Models/Character.php`
```php
public function toAgentContext(): array
{
    return [
        'name' => (string) $this->name,
        'bio' => (string) ($this->bio ?? ''),
        'psychology' => (string) ($this->psychology ?? ''),
        'costume_notes' => (string) ($this->costume_notes ?? ''),
        'custom_attributes' => (array) ($this->custom_attributes ?? []),
    ];
}
```

### `app/Models/Location.php`
```php
public function toAgentContext(): array
{
    return [
        'name' => (string) $this->name,
        'type' => (string) ($this->type ?? 'INT'),
        'art_department_notes' => (string) ($this->art_department_notes ?? ''),
        'lighting_notes' => (string) ($this->lighting_notes ?? ''),
        'address_reference' => (string) ($this->address_reference ?? ''),
    ];
}
```

### `app/Providers/AppServiceProvider.php`
```php
public function boot(): void
{
    Model::preventAccessingMissingAttributes(! $this->app->isProduction());
    ...
}
```

### `jg_plugin_filmmaker/jg_plugin_filmmaker/structuring.py`
```python
formatted_chars: list[str] = []
for c in characters_context:
    if isinstance(c, dict) and "name" in c:
        details: list[str] = []
        if c.get("role"):
            details.append(f"Rol: {c['role']}")
        if c.get("bio"):
            details.append(f"Biografía: {c['bio']}")
        if c.get("psychology"):
            details.append(f"Psicología: {c['psychology']}")
        if c.get("costume_notes"):
            details.append(f"Vestuario: {c['costume_notes']}")
        desc = f"- {c['name']}"
        if details:
            desc += f" ({', '.join(details)})"
        formatted_chars.append(desc)
```

---

## 3. Pruebas Automatizadas

1. **Suite en Laravel (`tests/Unit/BibleContextContractTest.php`):**
   - Comprueba que `Character::toAgentContext()` retorna la estructura completa esperada.
   - Comprueba que `Location::toAgentContext()` retorna las notas de arte e iluminación.
   - Valida que `Model::preventAccessingMissingAttributes` arroja `MissingAttributeException` ante atributos no recuperados.
2. **Suite en Python (`jg_plugin_filmmaker`):**
   - Pytest con 13 tests pasando en `tests/test_filmmaker_tools.py` y `tests/test_plugin.py`.

Resultado: 100% de tests pasando en ambos componentes sin fallos de linters.
