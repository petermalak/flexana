<?php

namespace App\Services\Paymob;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class PaymobClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('paymob.base_url'), '/'),
            (string) config('paymob.api_key'),
        );
    }

    private function http(): PendingRequest
    {
        // DNS / connect issues are common in local dev; keep this resilient.
        return Http::timeout(30)
            ->connectTimeout(30)
            ->retry(2, 250)
            ->acceptJson();
    }

    public function authToken(): string
    {
        $res = $this->http()->post($this->baseUrl.'/api/auth/tokens', [
                'api_key' => $this->apiKey,
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Paymob auth failed: HTTP '.$res->status());
        }

        $token = (string) data_get($res->json(), 'token', '');
        if ($token === '') {
            throw new \RuntimeException('Paymob auth failed: missing token');
        }

        return $token;
    }

    /**
     * @return array{ id:int, amount_cents:int, currency:string, merchant_order_id?:string }
     */
    public function createOrder(string $authToken, array $payload): array
    {
        $res = $this->http()->post($this->baseUrl.'/api/ecommerce/orders', array_merge($payload, [
                'auth_token' => $authToken,
            ]));

        if (! $res->successful()) {
            throw new \RuntimeException('Paymob create order failed: HTTP '.$res->status());
        }

        $json = $res->json();
        $id = (int) data_get($json, 'id', 0);
        if ($id <= 0) {
            throw new \RuntimeException('Paymob create order failed: missing order id');
        }

        return [
            'id' => $id,
            'amount_cents' => (int) data_get($json, 'amount_cents', 0),
            'currency' => (string) data_get($json, 'currency', ''),
            'merchant_order_id' => (string) data_get($json, 'merchant_order_id', ''),
        ];
    }

    public function paymentKey(string $authToken, array $payload): string
    {
        $res = $this->http()->post($this->baseUrl.'/api/acceptance/payment_keys', array_merge($payload, [
                'auth_token' => $authToken,
            ]));

        if (! $res->successful()) {
            throw new \RuntimeException('Paymob payment key failed: HTTP '.$res->status());
        }

        $token = (string) data_get($res->json(), 'token', '');
        if ($token === '') {
            throw new \RuntimeException('Paymob payment key failed: missing token');
        }

        return $token;
    }

    public function transaction(string $authToken, int $transactionId): array
    {
        // Paymob uses `token` as query parameter for GET endpoints.
        $res = $this->http()->get($this->baseUrl.'/api/acceptance/transactions/'.$transactionId, [
                'token' => $authToken,
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Paymob fetch transaction failed: HTTP '.$res->status());
        }

        return (array) $res->json();
    }
}

