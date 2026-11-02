# Feature: Lectura Dramática Automatizada con Síntesis de Voz (TTS) Multivoz vía Speaches-AI

**Fecha:** 2026-11-02  
**Proyecto:** `jg_filmmaker` (Plataforma Web de Pipeline Cinematográfico Asistido por IA)  
**Autor:** Antigravity (Ingeniero Senior de IA y Audio)  
**Estado:** En Desarrollo  

---

## 1. Contexto y Objetivos

Dentro del estudio de guión de `jg_filmmaker`, los creadores necesitan escuchar auditivamente sus textos para validar el ritmo dramático, la sonoridad de los diálogos y la fluidez de las escenas sin coste adicional de APIs comerciales.

El objetivo de esta funcionalidad es integrar el motor local de síntesis de voz neural **`speaches-ai`** (alojado en el servidor bajo `ia/speaches`, compatible con la API de OpenAI Audio en `/v1/audio/speech`) para permitir:
1. **Lectura por bloque individual:** Escuchar un bloque narrativo o diálogo concreto al editarlo.
2. **Lectura por escena:** Escuchar la escena completa con reproducción secuencial encadenada.
3. **Lectura del guión completo:** Recorrido auditivo de toda la obra de principio a fin.
4. **Diferenciación de voces (Multivoz):** Distinguir auditivamente la voz del narrador (acotaciones/sluglines) de las voces de los distintos personajes (diálogos).

---

## 2. Arquitectura de la Solución

```mermaid
flowchart TD
    UI[ScriptEditor: Botones Play en Bloque / Escena / Barra Superior] --> JS[Web Audio Player en Frontend]
    JS --> EP[GET /projects/{project}/tts/stream]
    EP --> Auth{can:view, project?}
    Auth -- No --> Deny[403 Forbidden]
    Auth -- Sí --> Cache{Existe en tts_cache?}
    Cache -- Sí (Hit) --> Stream[Stream Audio WAV/MP3]
    Cache -- No (Miss) --> Service[TextToSpeechService]
    Service --> Parser[Detección Dramática: Narrador vs Personaje]
    Parser --> Speaches[POST http://speaches:8000/v1/audio/speech]
    Speaches --> Save[Persistencia en storage/app/tts_cache]
    Save --> Stream
    Stream --> JS
    JS --> Sync[Resaltado Visual en Vivo del Bloque en Lectura]
```

---

## 3. Desglose en 4 Fases de Implementación

### Fase 1: Servicio Core de Síntesis y Caché Determinista (`TextToSpeechService` & `AudioStreamController`)
- **Configuración (`config/services.php`):**
  - Declaración del servicio `speaches` con URL base interna (`http://speaches:8000` en producción, configurable vía `SPEACHES_URL`), modelo por defecto (`speaches-ai/piper-es_ES-sharvard-medium`) y voz por defecto (`sharvard#M`).
- **Servicio `TextToSpeechService`:**
  - Método `synthesize(string $text, string $voice = null, float $speed = 1.0): string` que genera o recupera el fichero de audio binario.
  - Mecanismo de caché determinista O(1) indexado por `sha256(voice . ':' . speed . ':' . trim($text)) . '.wav'` en `storage/app/private/tts_cache/`.
  - Reutilización instantánea (0 ms) ante textos no modificados.
- **Controlador de Streaming Seguro (`AudioStreamController`):**
  - Ruta protegida `GET /projects/{project}/tts/stream?block_id=...&segment=...` con middleware de autorización `can:view,project` para evitar exposición de propiedad intelectual.

### Fase 2: Motor de Parseo Dramático y Asignación de Voces por Personaje
- **Asignación en Biblia Dramática:**
  - Soporte de configuración de voz en `Character` (`voice_id`), permitiendo seleccionar entre las voces disponibles en castellano (`sharvard#M` para voces masculinas, `sharvard#F` para voces femeninas, o voces adicionales de Piper/Kokoro).
- **Segmentación Inteligente de Guión Literario (Screenplay):**
  - Separación entre acotaciones/sluglines y parlamentos de personajes.
  - Asociación de cada diálogo con la voz del personaje emisor (`CAMARERO`, `LUCÍA`, etc.).
  - Para bloques en Bruto (Raw) o Tratamiento (Structured), locución unificada con la voz del Narrador.

### Fase 3: Reproductor Interactivo y Experiencia de Usuario en `ScriptEditor`
- **Controles de Reproducción:**
  - Botón de reproducción rápida en cada bloque del guión.
  - Botón de reproducción de escena en la cabecera de cada escena (`playSceneAudio`).
  - Botón *"Lectura de Guión"* en la barra superior para reproducción de la obra completa.
- **Barra de Reproducción Flotante:**
  - Panel fijo inferior con controles: Play / Pausa / Detener, barra de progreso, botón de salto al siguiente bloque y selector de velocidad (1.0x, 1.25x).
  - Resaltado visual en tiempo real (*karaoke highlight*) del bloque actualmente en reproducción con scroll suave automático (*autoscroll*).

### Fase 4: Suite de Pruebas Automatizadas, Validación CI/CD y Despliegue
- Cobertura con pruebas unitarias y de integración para `TextToSpeechService` utilizando `Http::fake()` para aislamiento determinista en tests locales y CI/CD.
- Pruebas de autorización en `AudioStreamController` (mitigación IDOR).
- Formato con Laravel Pint (`./vendor/bin/pint --test`) y verificación con `php artisan test` (206+ tests).
- Despliegue en contenedor Docker en producción con purga de cachés.
