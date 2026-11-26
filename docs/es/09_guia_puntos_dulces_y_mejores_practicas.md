# 09. Guía de Puntos Dulces, Ritmo Audiovisual y Mejores Prácticas (`jg_filmmaker`)

[← 08. Autenticación, Admin y Telemetría](08_auth_admin_telemetry.md) | [Índice General]( ../README.md)

---

## 1. Propósito de esta Guía

`jg_filmmaker` da soporte a **dos flujos creativos complementarios** dentro del estudio:

1. **Vídeos IA y Escaletas Broadcast (`Videos IA`):** Creación ágil de píldoras divulgativas, crónicas informativas, debates sintéticos y piezas para emisión continua 24/7 o redes sociales, combinando locución neuronal continua (*Audio-First*), generación visual 16:9 *full-bleed* y montaje automatizado con FFmpeg.
2. **Guion Manual y Producción Cinematográfica (`Proyectos personales`):** Escritura autoral o asistida de cortometrajes, largometrajes, ficción sonora y documentales, controlando escena a escena el paso por *Bruto → Tratamiento → Guion Literario → Planos → Rodaje (Tomas) → Montaje Previo (NLE)*.

Esta guía recoge los **"puntos dulces" (*sweet spots*)** empíricos de duración, extensión de texto, densidad de planos, uso de la Biblia Contextual y rendimiento de renderizado para obtener la máxima calidad narrativa y visual en ambos mundos.

---

## 2. Mapa Comparativo Rápido: Puntos Dulces por Formato

| Formato / Caso de Uso | Duración Óptima | Extensión de Guion (Palabras) | Nº Ideal de Planos | Duración Media por Plano | Perfil de Salida Recomendado |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Short / Micro-Píldora Vertical** | `35 – 55 s` | `85 – 135 palabras` | `4 – 6 planos` | `7 – 10 s` (1–2 frases) | `9:16` (`1080x1920`, 30 fps) |
| **Píldora TV / Directo IA (*Sweet Spot*)** | `45 – 75 s` | `110 – 185 palabras` | `5 – 7 planos` | `8 – 12 s` (1–2 frases) | `720p30` (Directo) / `1080p30` (VOD) |
| **Crónica / Noticia Comentada IA** | `90 – 150 s` | `220 – 370 palabras` | `8 – 14 planos` | `9 – 12 s` (2 frases) | `720p30` / `1080p30` + Podcast MP3 |
| **Debate Multi-Voz IA** | `3 – 5 min` | `450 – 750 palabras` | `15 – 25 planos` | `10 – 14 s` (1 intervención) | `720p30` / `1080p30` + Podcast MP3 |
| **Escena de Ficción (Guion Manual)** | `1 – 2.5 min` | `130 – 320 palabras` (1–2.5 págs.) | `6 – 15 planos` | `4 – 10 s` según tensión | `1080p` + Export `.fcpxml` / `.edl` |

> **Regla base de cadencia oral en español:** Una locución natural e inteligible avanza a **`145 – 160 palabras por minuto`** (`~2.5 palabras por segundo`). Usa siempre esta equivalencia para dimensionar tus bloques antes de renderizar.

---

## 3. Recomendaciones para Usuarios de Vídeos IA y Escaletas Broadcast

### 3.1 El Punto Dulce de Emisión Continua (`45 a 75 segundos`)

Si produces contenido para un canal automatizado o directo continuo, diseña tus escaletas alrededor de **píldoras independientes de `45 a 75 segundos` (`5 a 7 planos`)**:

```text
Demasiado corta (< 25 s)             PUNTO DULCE (45 - 75 s)             Demasiado larga (> 4 min)
─────────────────────────         ─────────────────────────────         ──────────────────────────
• Alto sobrecoste fijo de         • 5 a 7 imágenes IA en paralelo       • Alto tiempo de espera hasta
  cola, LLM y contenedor.         • 1 sola pista TTS con prosodia         tener el primer vídeo listo.
• Cambios bruscos de tema           natural de principio a fin.         • Mayor impacto si falla un
  en la emisión en vivo.          • Ratio > 2.5x tiempo real:             plano intermedio o salta un
                                    sostiene directo sin cortes.          límite de cuota por minuto.
```

### 3.2 Escritura Orientada a Locución Continua (*Audio-First*)

En la arquitectura **Audio-First**, el sistema genera primero **toda la locución de un tirón** para conservar la respiración, entonación y musicalidad natural de la voz, y después ajusta la duración de cada plano visual al tiempo real del audio:

1. **Escribe siempre en frases completas y autónomas (`15 a 30 palabras` por frase):**
   - Evita frases subordinadas interminables de 70 palabras: dificultan tanto la respiración del TTS como el cambio natural de plano visual.
   - Agrupa **1 o 2 frases completas por plano visual** (`~8–12 segundos` en pantalla). Nunca fuerces más planos visuales que frases reales tenga el texto.
2. **Separa lo que se escucha de lo que se lee en pantalla:**
   - **No incluyas en el cuerpo oral** URLs (`https://...`), avisos legales (`Fuente: Wikipedia bajo licencia CC BY-SA 4.0`), corchetes de notas internas ni tablas Markdown crudas.
   - Los créditos de atribución, fuentes y datos numéricos densos deben viajar como **metadatos visuales / faldones** sobreimpresos en vídeo, jamás dentro del texto locutado.
3. **Cuida cifras, siglas y extranjerismos:**
   - Si una sigla debe leerse deletreada o como palabra, escríbelo de forma fonéticamente inequívoca en borradores críticos (ej. `API`, `SQL`, `open source`).
   - En debates multi-voz, mantén intervenciones ágiles de `25 a 55 palabras` por ponente para que la conversación tenga ritmo televisivo y no parezca una sucesión de monólogos.

### 3.3 Estética Visual 16:9 Full-Bleed y Consistencia con la Biblia

Para evitar imágenes inconexas o con aspecto de boceto técnico con textos incrustados:

1. **Alimenta siempre la Biblia del Proyecto antes de generar imágenes:**
   - Revisa los **Personajes** (presentador, analistas, protagonistas) y **Localizaciones** generados en el proyecto.
   - Añade notas concretas de **vestuario, rasgos físicos, iluminación y dirección de arte**, y sube una **imagen de referencia** cuando quieras fijar la identidad exacta de un presentador o plató entre distintos episodios.
2. **Define una Dirección de Arte explícita (no la dejes al azar):**
   - Elige un preset estético coherente con el canal (fotografía documental 35mm, ilustración editorial limpia, *sci-fi* pictórico, etc.) especificando **paleta de color dominante** y **tipo de iluminación**.
   - Solicita siempre encuadres **16:9 a sangre (*full-bleed*) libres de texto**, evitando pedir carteles, rótulos o pantallas con palabras legibles dentro de la propia ilustración IA (los titulares y subtítulos los añade FFmpeg con nitidez tipográfica perfecta).
3. **Itera en los Planos (*Shots*) antes del render completo:**
   - Entra al proyecto en `jg_filmmaker`, abre los planos clave (especialmente el **Plano 1 de apertura**, que marca el tono y sirve de ancla visual) y lanza pruebas individuales de imagen hasta dar con el estilo deseado.
   - Una vez aprobadas las imágenes de tus planos en el estudio, el renderizador del vídeo completo reutilizará instantáneamente esas imágenes validadas sin volver a gastar tokens ni alterar el resultado.

### 3.4 Operación de Directo sin Cortes (*Rolling Buffer*)

- **Perfil `720p30` para emisión 24/7 en vivo:** Reduce a la mitad la carga de CPU de codificación H.264 y el ancho de banda de subida RTMP manteniendo una nitidez excelente en streaming continuo; reserva `1080p30` para piezas bajo demanda (VOD) o estrenos puntuales.
- **Colchón mínimo en cola (*Lookahead Buffer*):** Mantén siempre entre **3 y 4 piezas terminadas por delante** (`~3–5 minutos` de vídeo ya validado en disco) y al menos un conjunto de píldoras atemporales (*Evergreen*) marcadas como respaldo automático ante picos de latencia en APIs externas.

---

## 4. Recomendaciones para Guionistas Manuales y Cineastas (`Proyectos personales`)

### 4.1 Granularidad Óptima de Escenas y Bloques (`ScriptBlock`)

El editor de estudio divide cada **Escena (`Scene`)** en **Bloques (`ScriptBlock`)**. Elegir bien el tamaño de cada bloque multiplica la precisión de la IA y tu comodidad al editar:

```text
[Escena: INT. LABORATORIO CENTRAL - NOCHE]
   │
   ├── Bloque 1 (Beat de apertura / atmósfera):   80 - 160 palabras  ──▶  2 - 4 planos
   ├── Bloque 2 (Conflicto / diálogo central):   120 - 220 palabras  ──▶  4 - 7 planos
   └── Bloque 3 (Giro dramático / salida):        60 - 140 palabras  ──▶  2 - 3 planos
```

- **Punto dulce por `ScriptBlock`: `80 a 220 palabras` (`1 beat dramático`).**
  - Si metes una escena entera de 1.200 palabras en un único `ScriptBlock`, cualquier regeneración parcial afectará a toda la escena y el desglose de planos perderá sutileza.
  - Si divides cada *beat* o cambio de intención dramática en su propio bloque, puedes **bloquear con candado (`user_locks`)** los bloques que ya están redondos y pedir asistencia IA únicamente en el bloque que estás reescribiendo.
- **Equivalencia cinematográfica clásica:** En la etapa de **Guion Literario** (formato Fountain / Courier 12pt), **1 página equivale aproximadamente a 1 minuto en pantalla** (`~150–180 palabras` combinando acción y diálogo).

### 4.2 Orden de Trabajo Recomendado en Proyectos Manuales

1. **Paso 1 — Crea primero la Biblia Contextual (Personajes y Localizaciones):**
   - Antes de pulsar *"Estructurar"* o *"Convertir a Guion"*, registra tus personajes principales (nombre canónico, psicología, vestuario, voz TTS asignada y foto/boceto de referencia) y tus escenarios clave (notas de arte e iluminación).
   - El pipeline inyectará automáticamente esa Biblia en todas las herramientas: respetará los nombres en mayúsculas en el guion, evitará contradicciones visuales y asignará a cada personaje su propia voz en la lectura dramática (`Exportar MP3`).
2. **Paso 2 — Escribe en presente visual, no en pensamientos abstractos:**
   - En lugar de *"Elena recuerda con tristeza su infancia y duda si entrar"*, escribe acciones filmables: *"Elena se detiene frente a la puerta entreabierta. Aprieta el llavero oxidado contra la palma de la mano"*. Esto produce **mejores planos (`PD`, `PP`)** y **mejores viñetas visuales**.
3. **Paso 3 — Usa los Candados (`user_locks`) al pulir diálogos:**
   - Si has afinado manualmente una réplica brillante en **Guion Literario**, bloquéala antes de re-ejecutar lotes o revisiones de agujeros de guion (*Plot Holes*). El sistema preservará intacta tu escritura autoral.

### 4.3 Cobertura de Cámara y Densidad de Planos (`Shots`)

Al pasar a la etapa de **Planos Técnicos**, equilibra los tipos de encuadre según la intención emocional de la escena:

| Código | Tipo de Plano | Cuándo usarlo en tu desglose | Duración orientativa en montaje |
| :--- | :--- | :--- | :--- |
| **`PG`** | Plano General | Situar geografía al abrir escena, mostrar aislamiento o coreografía grupal. | `5 – 9 s` |
| **`PA`** | Plano Americano | Personajes en movimiento, desplazamientos o acción física de medio cuerpo. | `4 – 7 s` |
| **`PM`** | Plano Medio | Conversación estándar, lenguaje corporal y relación entre dos personajes. | `4 – 8 s` |
| **`PP`** | Primer Plano | Clímax emocional, subtexto, duda, reacción silenciosa (corta a `PP` en el giro). | `3 – 6 s` |
| **`PD`** | Plano Detalle | Objetos narrativos clave (*atrezzo*), pistas visuales, relojes, pantallas, manos. | `1.5 – 3.5 s` |

- **Consejo de ritmo:** Una escena dialogada de 90 segundos funciona de forma óptima con **8 a 14 planos planificados** (combinando un `PG` maestro, cobertura en `PM`, `PP` para reacciones y 1–2 `PD` de énfasis).
- **Previsualización con *Atrezzo*:** En el modal visual de cada plano puedes subir hasta **3 imágenes de objetos (`@Atrezzo1..3`)** además de vincular Personaje y Localización de la Biblia para comprobar la composición exacta de cámara antes de ir a rodaje físico.

### 4.4 Del Rodaje (`Takes`) al Corte Previo (`RoughCutTimeline`)

- Sube las tomas grabadas en cada plano (`MP4`/`MOV`/`WebM`) y marca **`is_good_take = true` únicamente en la toma elegida** por cada plano que quieras incluir en la secuencia.
- Usa el **Montaje Previo (`RoughCutTimeline`)** para validar el ritmo real de la escena ensamblada con FFmpeg; cuando el corte funcione dramáticamente, exporta la línea de tiempo en **`.fcpxml` (Final Cut Pro / DaVinci Resolve)** o **`.edl` (CMX 3600 / Premiere)** para el etalonaje final y la mezcla de sonido en tu NLE de escritorio.
