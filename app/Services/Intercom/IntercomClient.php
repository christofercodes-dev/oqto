<?php

namespace App\Services\Intercom;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal klient för Intercoms REST API - bara det hjälpcentret behöver
 * (artiklar och samlingar). Båda endpointarna är paginerade.
 */
class IntercomClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function articles(): array
    {
        return $this->paginate('/articles');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collections(): array
    {
        return $this->paginate('/help_center/collections');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function paginate(string $path): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->http()->get($path, ['page' => $page, 'per_page' => 50])->throw();

            $json = $response->json();

            array_push($items, ...($json['data'] ?? []));

            $totalPages = (int) ($json['pages']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $items;
    }

    protected function http(): PendingRequest
    {
        $token = config('services.intercom.token');

        if (blank($token)) {
            throw new RuntimeException('INTERCOM_ACCESS_TOKEN saknas i .env.');
        }

        return Http::baseUrl(rtrim((string) config('services.intercom.base_url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->withHeaders(['Intercom-Version' => (string) config('services.intercom.version')])
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }
}
