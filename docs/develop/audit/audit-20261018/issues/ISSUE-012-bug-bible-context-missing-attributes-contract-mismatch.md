# [ISSUE-012] Contexto de Biblia roto: atributos inexistentes (`role`, `personality_notes`, `description`) y claves incompatibles con el plugin (`bio`)

- **Severidad:** Alta
- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Ubicación:** `app/Services/ScriptPipelineService.php:34-38,120-124,349-353`, `app/Livewire/Studio/ScriptEditor.php:1346-1352,1589-1599`, `app/Models/Character.php:22-29`, `jg_plugin_filmmaker/structuring.py` (`c.get('bio')`, `c.get('role')`), `docs/02_tools_specification.md` (plugin)
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
`Character` tiene `bio`, `psychology`, `costume_notes`, `custom_attributes`; `Location` tiene `art_department_notes`, `lighting_notes`, `address_reference`. Sin embargo el código construye el contexto con atributos que **no existen**:
- `$c->role ?? 'personaje'` y `$c->personality_notes ?? ''` (ScriptPipelineService) → siempre `'personaje'` y `''`.
- `$c->role`, `$c->description`, `$l->description` en `analyzePlotHoles()` → `null`.
Eloquent devuelve `null` en silencio (no hay `Model::preventAccessingMissingAttributes()`).

Además, el plugin lee `c.get('bio')` y `c.get('role')`, pero Laravel envía `personality_notes`. Resultado: **la biografía, psicología y vestuario que el autor escribe en la Biblia jamás llegan al LLM**; la "Biblia dramática" es decorativa. La spec del plugin documenta `characters_context: list[str]` mientras el código usa `list[dict[str, Any]]` (ver ISSUE-034).

## 2. Evidencia de Código
```php
'role' => $c->role ?? 'personaje',            // Character no tiene 'role'
'personality_notes' => $c->personality_notes ?? '',   // no existe
...
'description' => $c->description,             // Character/Location no tienen 'description'
```
```python
f"- {c.get('name', '')}: {c.get('bio', '')} (Rol: {c.get('role', 'personaje')})"
```

## 3. Impacto Operativo o de Seguridad
Degradación silenciosa de la calidad de IA (personajes sin contexto), inconsistencias entre estructurado/guión/auditoría, falsos positivos de agujeros, y diagnóstico imposible porque nunca hay error.

## 4. Solución Recomendada
- Definir un único mapper `Character::toAgentContext()` / `Location::toAgentContext()` usado por pipeline y editor:
```php
public function toAgentContext(): array
{
    return ['name'=>$this->name,'bio'=>$this->bio,'psychology'=>$this->psychology,'costume_notes'=>$this->costume_notes];
}
```
- Alinear el esquema Pydantic del plugin (`CharacterContext`, `LocationContext`) con ese contrato y versionarlo.
- Activar `Model::preventAccessingMissingAttributes(! app()->isProduction())` en `AppServiceProvider::boot()` para detectar esta clase de errores en tests/dev.
- Test que verifique que `bio` aparece en el `characters_context` enviado (Http::fake + assertSent).
