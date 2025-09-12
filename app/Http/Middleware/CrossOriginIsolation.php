<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CrossOriginIsolation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply strict CORS headers for pages that need FFmpeg (SharedArrayBuffer)
        $needsFFmpeg = $this->pageNeedsFFmpeg($request);

        // Skip in development mode to avoid conflicts with Vite dev server
        $skipCOEP = config('app.debug') === true && env('SKIP_COEP_IN_DEV', true);

        if ($needsFFmpeg && !$skipCOEP) {
            // Add headers required for SharedArrayBuffer and FFmpeg
            $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        }

        return $response;
    }

    /**
     * Determine if the current page needs FFmpeg functionality
     */
    private function pageNeedsFFmpeg(Request $request): bool
    {
        $path = $request->path();

        // Exclude simple versions that don't need FFmpeg
        if (str_contains($path, 'simple')) {
            return false;
        }

        // Only apply strict headers to specific routes that need FFmpeg
        $ffmpegRoutes = [
            'new-meeting'
        ];

        foreach ($ffmpegRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }
}
