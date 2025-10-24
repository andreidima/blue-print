<?php

namespace App\Services\WooCommerce;

use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class Client
{
    public function __construct(
        protected readonly string $url,
        protected readonly string $consumerKey,
        protected readonly string $consumerSecret,
        protected readonly string $version,
        protected readonly int $perPage = 50
    ) {
    }

    public static function fromConfig(): self
    {
        $config = config('woocommerce');

        return new self(
            url: rtrim($config['url'] ?? '', '/'),
            consumerKey: $config['consumer_key'] ?? '',
            consumerSecret: $config['consumer_secret'] ?? '',
            version: trim($config['version'] ?? 'wc/v3', '/'),
            perPage: (int) ($config['per_page'] ?? 50),
        );
    }

    public function getOrders(array $params = []): array
    {
        return $this->paginatedRequest('orders', $params);
    }

    public function getOrdersUpdatedSince(?Carbon $since): array
    {
        $params = [];

        if ($since !== null) {
            $params['modified_after'] = $since->copy()->utc()->toIso8601ZuluString();
        }

        $params['orderby'] = Arr::get($params, 'orderby', 'date');
        $params['order'] = Arr::get($params, 'order', 'asc');

        return $this->getOrders($params);
    }

    public function getOrderStatuses(): array
    {
        $response = $this->request()->get($this->endpoint('orders/statuses'));

        if ($response->failed()) {
            throw new WooCommerceRequestException(
                sprintf('WooCommerce order statuses request failed: %s', $response->body()),
                $response
            );
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    public function updateOrder(int $orderId, array $payload): array
    {
        $response = $this->request()->put($this->endpoint("orders/{$orderId}"), $payload);

        if ($response->failed()) {
            throw new WooCommerceRequestException(
                sprintf('WooCommerce update order request failed: %s', $response->body()),
                $response
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new WooCommerceRequestException(
                'WooCommerce update order request returned an unexpected response.',
                $response
            );
        }

        return $data;
    }

    protected function paginatedRequest(string $resource, array $params = []): array
    {
        $results = [];
        $page = 1;

        do {
            $response = $this->request()->get($this->endpoint($resource), array_merge($params, [
                'per_page' => $this->perPage,
                'page' => $page,
            ]));

            if ($response->failed()) {
                throw new WooCommerceRequestException(
                    sprintf('WooCommerce %s request failed: %s', $resource, $response->body()),
                    $response
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                break;
            }

            $results = array_merge($results, $data);
            $totalPages = (int) ($response->header('X-WP-TotalPages') ?? $page);
            $page++;
        } while ($page <= max($totalPages, 1));

        return $results;
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->apiBase())
            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->acceptJson();
    }

    protected function endpoint(string $resource): string
    {
        return $resource;
    }

    protected function apiBase(): string
    {
        return $this->url.'/wp-json/'.$this->version;
    }
}
