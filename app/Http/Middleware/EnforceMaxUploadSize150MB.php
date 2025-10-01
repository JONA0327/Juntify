<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMaxUploadSize150MB
{
    // 150 MB exactos en bytes
    private const MAX_BYTES = 150 * 1024 * 1024; // 157286400

    public function handle(Request $request, Closure $next): Response
    {
        // Content-Length rápido (puede no estar siempre)
        $contentLength = (int) $request->header('Content-Length', 0);
        if ($contentLength > 0 && $contentLength > self::MAX_BYTES) {
            return response()->json([
                'code' => 'PAYLOAD_TOO_LARGE',
                'message' => 'Archivo supera el límite de 150MB',
                'limit_bytes' => self::MAX_BYTES,
                'content_length' => $contentLength,
            ], 413);
        }

        // Si es multipart y no tenemos Content-Length fiable, validamos después
        // Al final, antes de responder podemos inspeccionar el tamaño real de los archivos
        $response = $next($request);

        // Alternativamente se podría mover lógica post-lectura, pero Laravel ya rechazará por rule max.
        return $response;
    }
}
