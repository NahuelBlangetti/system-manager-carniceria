<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Balanza por defecto
    |--------------------------------------------------------------------------
    |
    | Clave dentro de "connections" a usar cuando no se especifica una
    | balanza en particular. Preparado para poder agregar más balanzas
    | en el futuro (distinta IP, puerto o divisor) sin tocar el servicio.
    |
    */

    'default' => env('SCALE_CONNECTION', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Store de lecturas
    |--------------------------------------------------------------------------
    |
    | Caché donde el daemon `scale:watch` publica el peso y desde donde lo
    | leen las peticiones HTTP. Por defecto se usa 'file' y no el caché de
    | la aplicación: el vigilante escribe varias veces por segundo de forma
    | indefinida, y eso no debería ir contra la tabla `cache` de MySQL
    | compitiendo con el resto del sistema.
    |
    */

    'store' => env('SCALE_CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Sondeo del navegador
    |--------------------------------------------------------------------------
    |
    | Cada cuánto la pantalla le pide el peso al servidor. Alineado con
    | scale.watch.interval_ms (el daemon publica cada 200ms): preguntar más
    | rápido que eso solo repite el mismo dato, y preguntar más lento suma
    | latencia percibida sin necesidad.
    |
    */

    'poll_ms' => env('SCALE_POLL_MS', 200),

    /*
    |--------------------------------------------------------------------------
    | Vigilancia continua (daemon scale:watch)
    |--------------------------------------------------------------------------
    */

    'watch' => [

        // Período de muestreo. La balanza actualiza su display varias veces
        // por segundo; 200ms alcanza para que el peso se vea fluido en
        // pantalla sin saturar el adaptador serial-a-TCP.
        'interval_ms' => env('SCALE_WATCH_INTERVAL_MS', 200),

        // Timeouts del daemon, más cortos que los del camino HTTP: conviene
        // fallar rápido y reintentar en el ciclo siguiente antes que trabar
        // el bucle que también atiende a la otra balanza.
        'read_timeout_ms' => env('SCALE_WATCH_READ_TIMEOUT_MS', 800),
        'connect_timeout_ms' => env('SCALE_WATCH_CONNECT_TIMEOUT_MS', 1000),

        // Antigüedad a partir de la cual una lectura publicada se descarta.
        // Es lo que hace que, si el daemon muere o pierde la balanza, la
        // pantalla muestre "sin respuesta" en vez de congelar un peso viejo
        // y dejar que se cobre.
        'stale_after_ms' => env('SCALE_WATCH_STALE_AFTER_MS', 2000),

        // Backoff del corte de circuito ante fallos de conexión. Conectarse
        // a una IP donde no hay nada se come el timeout completo, así que
        // tras un fallo se espera (cada vez más) antes de reintentar.
        'retry_base_ms' => env('SCALE_WATCH_RETRY_BASE_MS', 500),
        'retry_max_ms' => env('SCALE_WATCH_RETRY_MAX_MS', 5000),

    ],

    /*
    |--------------------------------------------------------------------------
    | Detección de estabilidad
    |--------------------------------------------------------------------------
    |
    | El comando de "peso + estabilidad" de Systel (0x07) no se pudo
    | confirmar contra este firmware, así que la estabilidad se deriva del
    | historial: el peso se considera quieto cuando las últimas N lecturas
    | caen dentro de la tolerancia indicada, expresada en divisiones de la
    | balanza (con divisor 1000, una división = 0,001 kg).
    |
    | Con el intervalo por defecto, 4 muestras equivalen a ~600ms de peso
    | quieto antes de habilitar la captura.
    |
    */

    'stability' => [
        'samples' => env('SCALE_STABILITY_SAMPLES', 4),
        'tolerance_divisions' => env('SCALE_STABILITY_TOLERANCE_DIVISIONS', 1),
    ],

    'connections' => [

        'main' => [
            // Nombre con el que el operador conoce esta balanza. Es lo que se
            // muestra en la pantalla de venta, así que conviene que diga
            // dónde está físicamente y no cómo se llama la conexión.
            'label' => env('SCALE_LABEL', 'Mostrador 1'),
            'host' => env('SCALE_HOST', '192.168.0.169'),
            'port' => env('SCALE_PORT', 8899),
            'timeout' => env('SCALE_TIMEOUT', 3),
            // Esta Clipse muestra 3 decimales en el visor (resolución de
            // 5g), y Systel transmite el peso "tal cual se ve en el visor,
            // en lo referido a decimales" — confirmado contra una foto real
            // del display (0,550 kg) comparada con el crudo recibido.
            'divisor' => env('SCALE_DIVISOR', 1000),
            'unit' => env('SCALE_UNIT', 'kg'),
            // Tiempo mínimo (ms) entre lecturas reales a la balanza: si se
            // pide el peso de nuevo dentro de esta ventana, se reutiliza la
            // última lectura en vez de abrir una nueva conexión TCP. Solo
            // aplica al camino de lectura directa (sin daemon), y se compara
            // con precisión de microsegundos.
            'min_interval_ms' => env('SCALE_MIN_INTERVAL_MS', 300),
        ],

        // Segunda balanza (ej. otro mostrador). El lock y el cache de
        // debounce de ScaleService están namespaced por nombre de conexión,
        // así que leer 'main' y 'scale_2' al mismo tiempo no se bloquea
        // entre sí — cada balanza tiene su propio candado y su propia
        // ventana de debounce.
        'scale_2' => [
            'label' => env('SCALE_2_LABEL', 'Mostrador 2'),
            'host' => env('SCALE_2_HOST', '192.168.0.170'),
            'port' => env('SCALE_2_PORT', 8899),
            'timeout' => env('SCALE_2_TIMEOUT', 3),
            'divisor' => env('SCALE_2_DIVISOR', 100),
            'unit' => env('SCALE_2_UNIT', 'kg'),
            'min_interval_ms' => env('SCALE_2_MIN_INTERVAL_MS', 300),
        ],

    ],

];
