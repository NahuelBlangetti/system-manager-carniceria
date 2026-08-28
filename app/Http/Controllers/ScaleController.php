<?php

namespace App\Http\Controllers;

use App\Services\ScaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScaleController extends Controller
{
    /**
     * Peso de una sola balanza.
     *
     * Se mantiene por compatibilidad; para mostrar dos balanzas a la vez
     * conviene `weights()`, que resuelve las dos en un único request.
     */
    public function weight(Request $request, ScaleService $scaleService): JsonResponse
    {
        $result = $scaleService->readWeight($request->query('connection'));

        if (! $result['success']) {
            return response()->json($result, 503);
        }

        return response()->json($result);
    }

    /**
     * Peso de varias balanzas en un solo request.
     *
     * Devuelve 200 aunque alguna balanza falle: el request se atendió
     * correctamente, y cada balanza reporta su propio estado. Que una esté
     * desconectada no es un error de la petición, y devolver 503 haría que
     * el cliente descarte también la lectura de la balanza que sí funciona.
     *
     * Cada balanza reporta en `watched` si tiene un vigilante activo, para
     * que la interfaz sepa si la estabilidad que recibe está medida sobre el
     * flujo completo de lecturas o si tiene que estimarla por su cuenta.
     */
    public function weights(Request $request, ScaleService $scaleService): JsonResponse
    {
        $configured = array_keys(config('scale.connections', []));

        $requested = array_filter(
            array_map('trim', explode(',', (string) $request->query('connections')))
        );

        // Solo se aceptan nombres que existan en la configuración, así el
        // parámetro no puede usarse para sondear claves de config arbitrarias.
        $connections = $requested === []
            ? $configured
            : array_values(array_intersect($requested, $configured));

        return response()->json([
            'scales' => $scaleService->readMany($connections),
        ]);
    }
}
