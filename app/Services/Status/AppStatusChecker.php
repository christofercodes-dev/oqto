<?php

namespace App\Services\Status;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Kontrollerar om appen (app.oqto.se) svarar. Körs på servern i stället för i
 * besökarens webbläsare: webbläsaren får inte läsa svarskoden från en annan
 * domän (CORS), så den kan bara se "nåbar eller inte". Servern ser även 5xx.
 *
 * Det är en kontroll av att appen svarar på adressen - inte av att varje
 * enskild funktion i appen fungerar. Alla svar under 500 räknas som att appen
 * är uppe (inloggningssidan kan t.ex. ge 200, 302 eller 401).
 *
 * Resultatet cachas en kort stund så att många besökare ger få anrop mot appen.
 */
class AppStatusChecker
{
    public const OPERATIONAL = 'operational';

    public const DEGRADED = 'degraded';

    public const DOWN = 'down';

    /**
     * @return array{status: string, http_status: ?int, response_ms: ?int, checked_at: string}
     */
    public function check(): array
    {
        $ttl = (int) config('services.status.cache_seconds', 20);

        return Cache::remember('status.app', $ttl, fn () => $this->probe());
    }

    /**
     * Senaste resultatet om det finns i cachen - utan att göra en ny kontroll.
     * Används när sidan ritas upp, så att den aldrig väntar på appen.
     *
     * @return array{status: string, http_status: ?int, response_ms: ?int, checked_at: string}|null
     */
    public function cached(): ?array
    {
        return Cache::get('status.app');
    }

    /**
     * @return array{status: string, http_status: ?int, response_ms: ?int, checked_at: string}
     */
    protected function probe(): array
    {
        $started = microtime(true);

        try {
            $response = Http::connectTimeout(3)
                ->timeout((int) config('services.status.timeout', 5))
                ->withHeaders(['User-Agent' => 'Oqto-Status/1.0 (+https://oqto.se/support/status)'])
                ->get((string) config('services.status.url'));

            $httpStatus = $response->status();
        } catch (Throwable) {
            $httpStatus = null;
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);

        return [
            'status' => $this->classify($httpStatus, $elapsed),
            'http_status' => $httpStatus,
            'response_ms' => $httpStatus === null ? null : $elapsed,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    protected function classify(?int $httpStatus, int $elapsedMs): string
    {
        if ($httpStatus === null || $httpStatus >= 500) {
            return self::DOWN;
        }

        return $elapsedMs > (int) config('services.status.slow_ms', 3000) ? self::DEGRADED : self::OPERATIONAL;
    }
}
