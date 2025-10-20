<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IncreaseUploadLimits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Aumentar límites significativamente para uploads de archivos grandes
        set_time_limit(600); // 10 minutos
        ini_set('memory_limit', '2048M'); // 2GB de memoria para archivos muy grandes
        ini_set('max_execution_time', 600);
        ini_set('max_input_time', 600);
        ini_set('post_max_size', '500M');
        ini_set('upload_max_filesize', '500M');

        return $next($request);
    }
}
