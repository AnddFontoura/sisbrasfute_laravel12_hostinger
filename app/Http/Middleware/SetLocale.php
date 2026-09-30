<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines the application locale for each request so the API can respond
 * in the language chosen by the client (bilingual support).
 *
 * The locale is resolved, in order of priority, from:
 *   1. The "X-Locale" header (explicit override, e.g. "pt_BR" or "en").
 *   2. The standard "Accept-Language" header (e.g. "pt-BR,pt;q=0.9,en;q=0.8").
 *
 * If no supported locale is found, the application default is kept.
 */
class SetLocale
{
    /**
     * Locales supported by the application.
     * Keys are normalized (lowercase) client values; values are the
     * locale directories under /lang.
     */
    private const SUPPORTED = [
        'pt' => 'pt_BR',
        'pt-br' => 'pt_BR',
        'pt_br' => 'pt_BR',
        'en' => 'en',
        'en-us' => 'en',
        'en_us' => 'en',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveFromHeader($request->header('X-Locale'))
            ?? $this->resolveFromAcceptLanguage($request->header('Accept-Language'));

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Resolves a single explicit locale value against the supported list.
     */
    private function resolveFromHeader(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return self::SUPPORTED[strtolower(trim($value))] ?? null;
    }

    /**
     * Parses an Accept-Language header, honoring the client's quality
     * preference order, and returns the first supported locale.
     */
    private function resolveFromAcceptLanguage(?string $header): ?string
    {
        if (empty($header)) {
            return null;
        }

        $languages = [];

        foreach (explode(',', $header) as $part) {
            $segments = explode(';q=', trim($part));
            $tag = strtolower(trim($segments[0]));

            if ($tag === '') {
                continue;
            }

            $quality = isset($segments[1]) ? (float) $segments[1] : 1.0;
            $languages[$tag] = $quality;
        }

        arsort($languages);

        foreach (array_keys($languages) as $tag) {
            if (isset(self::SUPPORTED[$tag])) {
                return self::SUPPORTED[$tag];
            }

            // Fall back to the primary subtag (e.g. "pt" from "pt-pt").
            $primary = explode('-', $tag)[0];
            if (isset(self::SUPPORTED[$primary])) {
                return self::SUPPORTED[$primary];
            }
        }

        return null;
    }
}
