<?php

namespace App\Http\Middleware;

use App\Services\Foretagare\FeatureCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Styr vem som får visa våra sidor i en iframe.
 *
 * Alla sidor får visas i en ram bara från oqto.se självt. Undantaget är
 * byråsidorna (/byra/{byrå}/...) för byråer som har "Tillåt inbäddning" påslaget:
 * de får visas på vilken sajt som helst.
 *
 * Rubrikerna sätts här och inte i nginx, eftersom en nginx-rubrik gäller alla
 * sidor lika och inte kan slås av per byrå. Därför ska raden
 * `add_header X-Frame-Options "SAMEORIGIN";` inte finnas kvar i nginx-konfigurationen.
 */
class FrameOptions
{
    public function __construct(protected FeatureCatalog $catalog) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->embeddable($request)) {
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        return $response;
    }

    protected function embeddable(Request $request): bool
    {
        if (! $request->is('byra/*')) {
            return false;
        }

        $agency = $this->catalog->agency((string) $request->segment(2));

        return $agency !== null && $agency->get('allow_embed') === true;
    }
}
