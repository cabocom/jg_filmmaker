<div class="min-h-[85vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <!-- Tarjeta Central de Acceso -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl overflow-hidden p-8 space-y-6">
            
            <!-- Encabezado de la Tarjeta -->
            <div class="text-center space-y-2">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 mb-2">
                    <span class="text-2xl">🎬</span>
                </div>
                <h2 class="text-xl font-bold font-mono tracking-tight text-slate-900 dark:text-white uppercase">
                    jG Filmmaker
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Autenticación segura sin contraseñas (Passwordless OTP)
                </p>
            </div>

            <!-- Paso 1: Introducir Email -->
            @if($step === 'request')
                <form wire:submit.prevent="requestOtp" class="space-y-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 font-mono">
                            Correo Electrónico
                        </label>
                        <input wire:model="email"
                               type="email"
                               id="email"
                               required
                               autofocus
                               placeholder="tu-correo@ejemplo.com"
                               class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition font-sans" />
                        @error('email')
                            <p class="text-xs text-rose-500 mt-1.5 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="w-full py-2.5 px-4 rounded-xl font-bold text-xs font-mono uppercase tracking-wider bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                        <span wire:loading.remove>Enviar Código de Acceso ➔</span>
                        <span wire:loading>Generando código...</span>
                    </button>

                    <p class="text-[11px] text-center text-slate-400 dark:text-slate-500 pt-2 leading-relaxed">
                        Te enviaremos un código efímero de un solo uso con validez de 15 minutos para iniciar sesión al instante.
                    </p>
                </form>

            <!-- Paso 2: Verificar Código OTP -->
            @elseif($step === 'verify')
                <div class="space-y-5">
                    <!-- Banner Especial en Localhost / Desarrollo (Estilo jg_hub) -->
                    @if($this->exposeDevOtp() && $devOtpCode)
                        <div class="p-4 rounded-xl bg-amber-500/10 border-2 border-dashed border-amber-500/60 text-amber-900 dark:text-amber-200 text-xs space-y-2">
                            <div class="flex items-center justify-between font-mono font-bold text-[11px] uppercase tracking-wider">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    MODO DESARROLLO LOCAL
                                </span>
                                <span class="text-[10px] text-amber-600 dark:text-amber-400">LOCALHOST</span>
                            </div>
                            <p class="text-xs text-slate-700 dark:text-slate-300">
                                Tu código de acceso generado es:
                            </p>
                            <div class="flex items-center justify-between gap-2 pt-1">
                                <span class="font-mono text-base font-extrabold tracking-widest text-amber-600 dark:text-amber-400 px-2.5 py-1 bg-white/80 dark:bg-slate-950/80 rounded-lg border border-amber-500/30">
                                    {{ $devOtpCode }}
                                </span>
                                <button wire:click="fillDevCode"
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-[11px] font-mono transition cursor-pointer">
                                    Autocompletar Código
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Información de Destinatario -->
                    <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between text-xs">
                        <div class="truncate mr-2">
                            <span class="text-slate-500 dark:text-slate-400">Enviado a:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $email }}</span>
                        </div>
                        <button wire:click="changeEmail"
                                type="button"
                                class="text-amber-600 dark:text-amber-400 hover:underline font-mono text-[11px] shrink-0 cursor-pointer">
                            Cambiar
                        </button>
                    </div>

                    <!-- Formulario de Verificación -->
                    <form wire:submit.prevent="verifyOtp" class="space-y-4">
                        <div>
                            <label for="verification_code" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 font-mono">
                                Código de Acceso (XXXX-XXXX)
                            </label>
                            <input wire:model="verification_code"
                                   type="text"
                                   id="verification_code"
                                   required
                                   autofocus
                                   placeholder="ABCD-EFGH"
                                   class="w-full px-3.5 py-2.5 rounded-xl text-center text-lg font-mono font-bold tracking-widest uppercase bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition" />
                            @error('verification_code')
                                <p class="text-xs text-rose-500 mt-1.5 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="w-full py-2.5 px-4 rounded-xl font-bold text-xs font-mono uppercase tracking-wider bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                            <span wire:loading.remove>Verificar Código e Iniciar Sesión ➔</span>
                            <span wire:loading>Comprobando...</span>
                        </button>
                    </form>

                    <!-- Reenviar Código -->
                    <div class="text-center pt-2">
                        <button wire:click="requestOtp"
                                type="button"
                                class="text-xs text-slate-500 hover:text-amber-600 dark:hover:text-amber-400 transition font-mono cursor-pointer">
                            ¿No has recibido el correo? Reenviar código
                        </button>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
