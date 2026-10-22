<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lägger X-Robots-Tag: noindex på sidor som inte ska listas av sökmotorer
 * (se config/seo.php). Kompletterar noindex-metataggen som layouten skriver
 * ut för sidor med "Dölj för sökmotorer" påslaget - headern gäller även om
 * sidan skulle cachas eller någon hämtar den utan att läsa HTML.
 */
class NoIndexPaths
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is(config('seo.noindex_paths', []))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
