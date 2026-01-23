<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['es', 'en'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        if ($locale) {
            cookie()->queue(cookie('locale', $locale, 60 * 24 * 365));
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = $this->normalizeLocale(Auth::user()?->locale);
        if ($userLocale) {
            return $userLocale;
        }

        $cookieLocale = $this->normalizeLocale($request->cookie('locale'));
        if ($cookieLocale) {
            return $cookieLocale;
        }

        $acceptLanguage = strtolower((string) $request->header('Accept-Language', ''));
        if (str_starts_with($acceptLanguage, 'en')) {
            return 'en';
        }
        if (str_starts_with($acceptLanguage, 'es')) {
            return 'es';
        }

        return 'es';
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        $locale = strtolower(trim($locale));
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0] ?? $locale;
        }

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : null;
    }
}
