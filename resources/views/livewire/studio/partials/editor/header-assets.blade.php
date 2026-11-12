    <style>
        /* Estilos directos e infalibles para hover de bloques */
        .script-block-item {
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .script-block-item:not(.is-selected):hover {
            background-color: #f1f5f9 !important; /* Tailwind slate-100 */
        }
        .dark .script-block-item:not(.is-selected):hover {
            background-color: #1e293b !important; /* Tailwind slate-800 */
        }
        .script-block-item.is-selected {
            background-color: rgba(245, 158, 11, 0.1) !important;
            border-left-color: #f59e0b !important;
        }

        /* Divisores Gutenberg: línea horizontal continua ámbar garantizada */
        .gutenberg-container {
            position: relative;
            z-index: 5 !important;
        }
        .gutenberg-divider-line {
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 2px !important;
            background-color: #f59e0b !important; /* amber-500 */
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            z-index: 5 !important;
        }
        .gutenberg-container:hover .gutenberg-divider-line,
        .gutenberg-divider-line.is-visible {
            opacity: 1 !important;
        }
        .gutenberg-container:hover .gutenberg-divider-btn,
        .gutenberg-divider-btn.is-visible {
            opacity: 1 !important;
            transform: scale(1) !important;
            pointer-events: auto !important;
            z-index: 6 !important;
        }
        [x-cloak] {
            display: none !important;
        }
    </style>

    <script>
        window.ttsPlayer = function({ projectId }) {
            return {
                projectId: projectId,
                isPlaying: false,
                isPaused: false,
                isLoading: false,
                manifest: [],
                currentIndex: 0,
                currentSpeaker: '',
                currentText: '',
                currentBlockId: null,
                speed: 1.0,
                audio: null,
                nextAudio: null,
                abortController: null,
                skipTimer: null,

                get progressText() {
                    if (!this.manifest.length) return '';
                    return `(${this.currentIndex + 1} de ${this.manifest.length})`;
                },

                async startPlayback(detail) {
                    detail = detail || {};
                    const scope = detail.scope || 'project';
                    const sceneId = detail.sceneId || '';
                    const blockId = detail.blockId || '';

                    this.stopPlayback();
                    this.isLoading = true;
                    this.isPlaying = true;
                    this.isPaused = false;
                    this.currentSpeaker = 'CARGANDO...';
                    this.currentText = 'Obteniendo locución...';
                    this.manifest = [];
                    this.currentIndex = 0;
                    this.abortController = new AbortController();

                    try {
                        const url = new URL(`/projects/${this.projectId}/tts/manifest`, window.location.origin);
                        if (sceneId) url.searchParams.set('scene_id', sceneId);
                        if (blockId) url.searchParams.set('block_id', blockId);

                        const res = await fetch(url.toString(), {
                            headers: { 'Accept': 'application/json' },
                            signal: this.abortController.signal
                        });

                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        const data = await res.json();
                        if (!data.manifest || data.manifest.length === 0) {
                            alert('No hay texto disponible para locutar.');
                            this.stopPlayback();
                            return;
                        }

                        this.manifest = data.manifest;
                        this.playSegment(0);
                    } catch (e) {
                        if (e.name !== 'AbortError') {
                            console.error('[TTS] Error iniciando locución:', e);
                            alert('No se pudo iniciar la locución: ' + e.message);
                        }
                        this.stopPlayback();
                    } finally {
                        this.isLoading = false;
                    }
                },

                getStreamUrl(index) {
                    if (index < 0 || index >= this.manifest.length) return null;
                    const seg = this.manifest[index];
                    let streamPath = seg.stream_url;
                    try {
                        const parsed = new URL(streamPath, window.location.origin);
                        streamPath = parsed.pathname + parsed.search;
                    } catch (e) {}
                    const streamUrl = new URL(streamPath, window.location.origin);
                    streamUrl.searchParams.set('speed', this.speed);
                    return streamUrl.toString();
                },

                preloadNext(index) {
                    const nextIdx = index + 1;
                    if (nextIdx >= this.manifest.length) return;
                    const nextUrl = this.getStreamUrl(nextIdx);
                    if (!nextUrl) return;

                    // Precarga nativa del navegador: crea un Audio con preload='auto'
                    this.nextAudio = new Audio();
                    this.nextAudio.preload = 'auto';
                    this.nextAudio.src = nextUrl;
                },

                playSegment(index) {
                    if (!this.isPlaying) return;
                    if (index < 0 || index >= this.manifest.length) {
                        this.stopPlayback();
                        return;
                    }

                    this.currentIndex = index;
                    const seg = this.manifest[index];
                    this.currentSpeaker = seg.speaker || 'NARRADOR';
                    this.currentText = seg.text || '';
                    this.currentBlockId = seg.block_id || null;

                    this.highlightBlock(this.currentBlockId);

                    // Si teníamos un audio previo sonando, detenerlo limpiamente
                    if (this.audio) {
                        this.audio.onended = null;
                        this.audio.onerror = null;
                        this.audio.pause();
                        this.audio = null;
                    }

                    // Reutilizar el nextAudio precargado si coincide la URL, o crear uno nuevo
                    const targetUrl = this.getStreamUrl(index);
                    if (this.nextAudio && this.nextAudio.src === targetUrl) {
                        this.audio = this.nextAudio;
                        this.nextAudio = null;
                    } else {
                        this.audio = new Audio(targetUrl);
                    }

                    this.audio.playbackRate = this.speed;

                    this.audio.onended = () => {
                        if (this.isPlaying && !this.isPaused) {
                            this.playSegment(this.currentIndex + 1);
                        }
                    };

                    this.audio.onerror = (e) => {
                        console.warn('[TTS] Audio error en segmento #' + this.currentIndex, e);
                        this.isPaused = true;
                    };

                    this.audio.play().catch(err => {
                        console.warn('[TTS] Error al iniciar play:', err);
                    });

                    // Precargar el siguiente de inmediato en el navegador
                    this.preloadNext(index);
                    this.isPaused = false;
                },

                togglePlayPause() {
                    if (!this.audio) {
                        this.playSegment(this.currentIndex);
                        return;
                    }
                    if (this.audio.paused) {
                        this.audio.play().catch(() => this.playSegment(this.currentIndex));
                        this.isPaused = false;
                    } else {
                        this.audio.pause();
                        this.isPaused = true;
                    }
                },

                nextSegment() {
                    if (this.currentIndex < this.manifest.length - 1) {
                        this.jumpToSegment(this.currentIndex + 1);
                    }
                },

                prevSegment() {
                    if (this.currentIndex > 0) {
                        this.jumpToSegment(this.currentIndex - 1);
                    }
                },

                jumpToSegment(index) {
                    this.currentIndex = index;
                    const seg = this.manifest[index];
                    if (seg) {
                        this.currentSpeaker = seg.speaker || 'NARRADOR';
                        this.currentText = seg.text || '';
                        this.currentBlockId = seg.block_id || null;
                        this.highlightBlock(this.currentBlockId);
                    }
                    if (this.skipTimer) {
                        clearTimeout(this.skipTimer);
                    }
                    if (this.audio) {
                        this.audio.onended = null;
                        this.audio.onerror = null;
                        this.audio.pause();
                        this.audio = null;
                    }
                    if (this.nextAudio) {
                        this.nextAudio.src = '';
                        this.nextAudio = null;
                    }
                    this.skipTimer = setTimeout(() => {
                        if (this.isPlaying) {
                            this.playSegment(this.currentIndex);
                        }
                    }, 180);
                },

                cycleSpeed() {
                    const speeds = [1.0, 1.25, 1.5];
                    const nextIdx = (speeds.indexOf(this.speed) + 1) % speeds.length;
                    this.speed = speeds[nextIdx];
                    if (this.audio) {
                        this.audio.playbackRate = this.speed;
                    }
                    this.preloadNext(this.currentIndex);
                },

                stopPlayback() {
                    if (this.skipTimer) {
                        clearTimeout(this.skipTimer);
                        this.skipTimer = null;
                    }
                    if (this.abortController) {
                        this.abortController.abort();
                        this.abortController = null;
                    }
                    if (this.audio) {
                        this.audio.onended = null;
                        this.audio.onerror = null;
                        this.audio.pause();
                        this.audio = null;
                    }
                    if (this.nextAudio) {
                        this.nextAudio.src = '';
                        this.nextAudio = null;
                    }
                    this.isPlaying = false;
                    this.isPaused = false;
                    this.isLoading = false;
                    this.manifest = [];
                    this.currentIndex = 0;
                    this.clearHighlight();
                    $wire.$refresh();
                },

                highlightBlock(blockId) {
                    this.clearHighlight();
                    if (!blockId) return;

                    const el = document.getElementById('block-' + blockId);
                    if (el) {
                        el.classList.add('tts-active-block', 'ring-2', 'ring-indigo-500', 'bg-indigo-500/10', 'dark:bg-indigo-950/40');
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                },

                clearHighlight() {
                    document.querySelectorAll('.tts-active-block').forEach(el => {
                        el.classList.remove('tts-active-block', 'ring-2', 'ring-indigo-500', 'bg-indigo-500/10', 'dark:bg-indigo-950/40');
                    });
                }
            };
        };

        if (window.Alpine) {
            window.Alpine.data('ttsPlayer', window.ttsPlayer);
        } else {
            document.addEventListener('alpine:init', () => {
                window.Alpine.data('ttsPlayer', window.ttsPlayer);
            });
        }
    </script>

