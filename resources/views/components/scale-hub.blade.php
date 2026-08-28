{{--
    Poller compartido de balanzas, más la fábrica de datos de Alpine que
    usan los componentes que muestran peso.

    Se incluye desde cualquier vista que necesite el peso en vivo. La
    directiva @once garantiza que el script se emita una sola vez por
    respuesta aunque haya varios lectores en pantalla, y el guard sobre
    window sobrevive a los re-render de Livewire.

    Consulta /scale/weights una vez por ciclo para TODAS las balanzas
    suscriptas: con dos balanzas eso es la mitad de requests, y sobre todo la
    mitad de escrituras a la tabla de sesiones, que en una PC de mostrador
    sondeando todo el día no es despreciable.

    Uso:
        <x-scale-hub />
        <div x-data="window.scaleHub.readerData('main', { pricePerKg: 9000 })">
            <span x-text="weight.toFixed(2)"></span>
            <button :disabled="! capturable">Tomar peso</button>
        </div>
--}}

@once
<script>
    window.scaleHub = window.scaleHub || (function () {
        let state = null;
        let timer = null;
        let inFlight = false;
        const connections = new Map();

        // Chrome (y derivados) frenan el setInterval de una pestaña en
        // segundo plano hasta una vez por minuto. Sin esto, volver a la
        // pestaña de venta después de mirar otra cosa deja el peso viejo en
        // pantalla hasta el próximo tick del timer, que puede tardar.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && timer) {
                poll();
            }
        });

        function ensureState() {
            if (! state) {
                // Alpine.reactive para que los getters de los componentes se
                // recalculen solos cuando llega una lectura nueva.
                state = Alpine.reactive({
                    scales: {},
                    transport: 'loading',
                    message: '',
                });
            }

            return state;
        }

        // Tiempo máximo que se le da a un ciclo antes de darlo por colgado.
        // Sin esto, un solo fetch que se cuelga (red, pestaña en segundo
        // plano, lo que sea) deja `inFlight` trabado y el polling entero se
        // congela en silencio hasta que ese request resuelva solo — que en
        // el navegador puede tardar mucho más que los 500ms del ciclo normal.
        const POLL_TIMEOUT_MS = 3000;

        // Guardia adicional, independiente del AbortController: si por lo
        // que sea `inFlight` sigue en true mucho después de vencido el
        // timeout (el abort no disparó, o quedó un then/catch sin correr),
        // se libera a la fuerza para que el ciclo siguiente no se salte.
        let inFlightSince = 0;

        async function poll() {
            if (inFlight) {
                if (Date.now() - inFlightSince < POLL_TIMEOUT_MS + 1000) {
                    return;
                }

                inFlight = false; // watchdog: se venció y nadie lo liberó
            }

            if (connections.size === 0) {
                return;
            }

            inFlight = true;
            inFlightSince = Date.now();

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), POLL_TIMEOUT_MS);

            try {
                const names = [...connections.keys()].join(',');
                const res = await fetch(`/scale/weights?connections=${encodeURIComponent(names)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal,
                });

                // 419 es el CSRF/sesión expirada de Laravel; para el operador
                // que dejó la pantalla abierta toda la tarde es lo mismo que un 401.
                if (res.status === 401 || res.status === 419) {
                    state.transport = 'unauthenticated';
                    stop();

                    return;
                }

                if (! res.ok) {
                    state.transport = 'error';
                    state.message = `El servidor respondió ${res.status}.`;

                    return;
                }

                const data = await res.json();

                state.scales = data.scales ?? {};
                state.transport = 'ok';
                state.message = '';
            } catch (e) {
                state.transport = 'error';
                state.message = e.name === 'AbortError'
                    ? 'La balanza tardó demasiado en responder.'
                    : 'No se pudo contactar al servidor.';
            } finally {
                clearTimeout(timeoutId);
                inFlight = false;
            }
        }

        function stop() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function subscribe(connection, pollMs) {
            const current = ensureState();

            connections.set(connection, (connections.get(connection) ?? 0) + 1);

            if (! timer) {
                poll();
                timer = setInterval(poll, pollMs);
            }

            return current;
        }

        function unsubscribe(connection) {
            const remaining = (connections.get(connection) ?? 1) - 1;

            if (remaining > 0) {
                connections.set(connection, remaining);
            } else {
                connections.delete(connection);
            }

            if (connections.size === 0) {
                stop();
            }
        }

        return {
            subscribe,
            unsubscribe,

            /*
             * Datos de Alpine para un lector de balanza. El spread de las
             * opciones va antes de los getters para que las opciones puedan
             * aportar datos (pricePerKg, pollMs) pero nunca pisar la lógica.
             */
            readerData(connection, options = {}) {
                return {
                    connection,
                    hub: null,
                    pollMs: {{ max(100, (int) config('scale.poll_ms', 500)) }},
                    stabilitySamples: {{ max(2, (int) config('scale.stability.samples', 4)) }},
                    toleranceDivisions: {{ max(1, (int) config('scale.stability.tolerance_divisions', 1)) }},
                    decimals: 2,
                    divisor: 100,
                    selected: false,
                    pricePerKg: 0,
                    samples: [],
                    lastReadAt: null,

                    ...options,

                    init() {
                        this.hub = subscribe(this.connection, this.pollMs);
                        this.$cleanup(() => unsubscribe(this.connection));
                    },

                    get reading() {
                        return this.hub?.scales?.[this.connection] ?? null;
                    },

                    /* Cuentas crudas de la balanza, tal como llegaron. Es lo
                     * que se manda al servidor como verificación cruzada al
                     * congelar el peso. */
                    get raw() {
                        return this.reading?.success ? String(this.reading.raw) : '';
                    },

                    get weight() {
                        return this.reading?.success ? this.reading.weight : 0;
                    },

                    get unit() {
                        return this.reading?.unit ?? 'kg';
                    },

                    /*
                     * El número se arma desde las cuentas crudas y el divisor,
                     * no desde el float: es exactamente lo que hizo el
                     * servidor, y así 1250 / 1000 se ve "1,250" y no "1,25".
                     */
                    get weightLabel() {
                        const decimals = Number.isInteger(this.decimals) ? this.decimals : 2;
                        const zero = (0).toFixed(decimals).replace('.', ',');

                        if (this.status !== 'ok') {
                            return zero;
                        }

                        const raw = parseInt(this.raw, 10);
                        const divisor = this.divisor > 0 ? this.divisor : (10 ** decimals);
                        const value = Number.isFinite(raw) ? raw / divisor : this.weight;

                        return value.toFixed(decimals).replace('.', ',');
                    },

                    get status() {
                        if (this.hub?.transport === 'unauthenticated') return 'unauthenticated';
                        if (this.hub?.transport === 'error') return 'error';
                        if (this.hub?.transport === 'loading' || ! this.reading) return 'loading';

                        return this.reading.success ? 'ok' : 'error';
                    },

                    get errorMessage() {
                        if (this.hub?.transport === 'error') return this.hub.message;

                        return this.reading?.success === false ? this.reading.message : '';
                    },

                    get watcherAlive() {
                        return Boolean(this.reading?.watched);
                    },

                    get isStable() {
                        if (! this.reading?.success) return false;

                        /*
                         * Con el daemon vivo, la estabilidad viene medida sobre
                         * el flujo completo de lecturas y es la fuente
                         * confiable. Sin daemon se estima acá, con muchas menos
                         * muestras y por lo tanto peor.
                         */
                        return this.watcherAlive ? Boolean(this.reading.stable) : this.locallyStable();
                    },

                    /*
                     * Estado visual de la tarjeta. El operador mira dos
                     * mostradores a la vez: el color tiene que decir si hay
                     * peso listo, si se mueve, o si esa balanza no está.
                     */
                    get tone() {
                        if (this.status === 'unauthenticated' || this.status === 'error') return 'error';
                        if (this.status === 'loading') return 'loading';
                        if (this.weight > 0 && this.isStable) return 'ready';
                        if (this.weight > 0) return 'moving';

                        return 'idle';
                    },

                    get shortStatus() {
                        if (this.status === 'unauthenticated') return 'sesión vencida';

                        return {
                            error: 'sin conexión',
                            loading: 'conectando',
                            ready: 'estable',
                            moving: 'moviéndose',
                            idle: 'conectada',
                        }[this.tone];
                    },

                    /* Único criterio para habilitar el botón de congelar peso. */
                    get capturable() {
                        return this.status === 'ok' && this.weight > 0 && this.isStable;
                    },

                    /*
                     * Por qué el botón está deshabilitado. Un botón gris sin
                     * explicación hace que el operador crea que el sistema se
                     * colgó, y termine tipeando el peso a mano.
                     */
                    get hint() {
                        if (this.status === 'unauthenticated') return 'Tu sesión venció — recargá la página.';
                        if (this.status === 'error') return this.errorMessage || 'La balanza no responde.';
                        if (this.status === 'loading') return 'Conectando con la balanza…';
                        if (this.weight <= 0) return 'Poné el producto en la balanza.';
                        if (! this.isStable) return 'Esperando que el peso se estabilice…';

                        return 'Peso estable — listo para tomar.';
                    },

                    get projectedTotal() {
                        return (this.weight * this.pricePerKg).toLocaleString('es-AR', {
                            style: 'currency', currency: 'ARS',
                        });
                    },

                    locallyStable() {
                        const reading = this.reading;

                        /*
                         * Se cuentan solo lecturas distintas, identificadas por
                         * read_at: si se contaran los ciclos de sondeo, una
                         * respuesta repetida del caché satisfaría la
                         * estabilidad sola y el chequeo no valdría nada.
                         */
                        if (reading.read_at !== this.lastReadAt) {
                            this.lastReadAt = reading.read_at;
                            // Cuentas crudas y no kg: comparar floats haría que
                            // una variación de exactamente una división no
                            // entre en una tolerancia de una división.
                            this.samples.push(parseInt(reading.raw, 10));
                            this.samples = this.samples.slice(-this.stabilitySamples);
                        }

                        if (this.samples.length < this.stabilitySamples) return false;

                        return (Math.max(...this.samples) - Math.min(...this.samples)) <= this.toleranceDivisions;
                    },
                };
            },
        };
    })();
</script>
@endonce
