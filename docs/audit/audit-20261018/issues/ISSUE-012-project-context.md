# Project Context: [ISSUE-012] Sincronización del contrato de Biblia dramática y prevención de atributos faltantes

## Resumen del Cambio
Se corrigió la desincronización entre el modelo relacional de la Biblia dramática (`Character` y `Location`) y el contrato esperado por las herramientas cognitivas de IA. Se crearon los métodos canónicos `toAgentContext()` en `Character` y `Location`, erradicando el acceso a propiedades espurias como `role`, `personality_notes` y `description`. En `jg_plugin_filmmaker`, se adaptó el formateo de prompts para inyectar con precisión la biografía, psicología, vestuario y notas de arte. Asimismo, se activó `Model::preventAccessingMissingAttributes()` en `AppServiceProvider` para abortar tempranamente ante accesos a columnas inexistentes en entornos no productivos.

## Archivos Modificados / Creados
- `jg_filmmaker/app/Models/Character.php`: Implementación de `toAgentContext()`.
- `jg_filmmaker/app/Models/Location.php`: Implementación de `toAgentContext()`.
- `jg_filmmaker/app/Services/ScriptPipelineService.php`: Sustitución de accesos directos por `toAgentContext()`.
- `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`: Sustitución de propiedades inexistentes en `analyzePlotHoles()`.
- `jg_filmmaker/app/Providers/AppServiceProvider.php`: Activación de `Model::preventAccessingMissingAttributes()`.
- `jg_filmmaker/database/factories/UserFactory.php`: Añadido valor por defecto `'is_admin' => false`.
- `jg_filmmaker/tests/Unit/BibleContextContractTest.php`: Suite unitaria validando contratos y excepciones de atributos.
- `jg_plugin_filmmaker/jg_plugin_filmmaker/structuring.py`: Enriquecimiento del prompt del LLM con datos reales de la Biblia.
- `jg_filmmaker/docs/audit/audit-20261018/issues/ISSUE-012-analysis.md`: Análisis técnico.
- `jg_filmmaker/docs/audit/audit-20261018/issues/ISSUE-012-walkthrough.md`: Guía de implementación.
- `jg_filmmaker/docs/audit/audit-20261018/issues/ISSUE-012-project-context.md`: Este documento de contexto.

## Impacto en Calidad y Arquitectura
- **Efectividad Real de la Biblia Dramática:** El LLM ahora recibe efectivamente la psicología y biografía de los personajes en cada transformación narrativa.
- **Detección Temprana de Bugs:** Cero regresiones por lectura de campos inexistentes gracias a `preventAccessingMissingAttributes()`.
