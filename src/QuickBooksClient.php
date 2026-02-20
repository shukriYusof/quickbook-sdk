<?php

namespace QuickBooks\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Arr;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use QuickBooks\SDK\Concerns\ParsesDate;
use QuickBooks\SDK\Contracts\TokenStoreInterface;
use QuickBooks\SDK\Exceptions\ApiException;
use QuickBooks\SDK\Exceptions\AuthenticationException;
use QuickBooks\SDK\Exceptions\RateLimitException;
use QuickBooks\SDK\OAuth\OAuth2Handler;
use QuickBooks\SDK\Resources\Account;
use QuickBooks\SDK\Resources\Bill;
use QuickBooks\SDK\Resources\CreditMemo;
use QuickBooks\SDK\Resources\Customer;
use QuickBooks\SDK\Resources\Employee;
use QuickBooks\SDK\Resources\Estimate;
use QuickBooks\SDK\Resources\Invoice;
use QuickBooks\SDK\Resources\Payment;
use QuickBooks\SDK\Resources\PurchaseOrder;
use QuickBooks\SDK\Resources\Vendor;

class QuickBooksClient
{
    use ParsesDate;

    private ?array $currentTokens = null;
    private ?Client $httpClient   = null;

    public function __construct(
        private string $qbCompanyId,
        private string $realmId,
        private string $environment,
        private TokenStoreInterface $tokenStore,
        private OAuth2Handler $oauth,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function invoices(): Invoice
    {
        return new Invoice($this);
    }

    public function customers(): Customer
    {
        return new Customer($this);
    }

    public function payments(): Payment
    {
        return new Payment($this);
    }

    public function accounts(): Account
    {
        return new Account($this);
    }

    public function vendors(): Vendor
    {
        return new Vendor($this);
    }

    public function bills(): Bill
    {
        return new Bill($this);
    }

    public function purchaseOrders(): PurchaseOrder
    {
        return new PurchaseOrder($this);
    }

    public function estimates(): Estimate
    {
        return new Estimate($this);
    }

    public function creditMemos(): CreditMemo
    {
        return new CreditMemo($this);
    }

    public function employees(): Employee
    {
        return new Employee($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function query(string $query): array
    {
        return $this->request('POST', 'query', [
            'query' => ['query' => $query],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, ['query' => $query]);
    }

    /**
     * @return array<string, mixed>
     */
    public function post(string $uri, array $payload = []): array
    {
        return $this->request('POST', $uri, ['json' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        $tokens = $this->ensureFreshToken();
        $url    = $this->buildUrl($uri);

        $headers                  = Arr::get($options, 'headers', []);
        $headers['Authorization'] = 'Bearer ' . $tokens['access_token'];
        $headers['Accept']        = 'application/json';
        $options['headers']       = $headers;

        $this->logger->debug('QuickBooks API: sending request.', [
            'method'       => $method,
            'url'          => $url,
            'qb_company_id' => $this->qbCompanyId,
        ]);

        try {
            $response = $this->client()->request($method, $url, $options);
        } catch (GuzzleException $e) {
            $statusCode = method_exists($e, 'getResponse') && $e->getResponse() !== null
                ? $e->getResponse()->getStatusCode()
                : 0;

            $this->logger->error('QuickBooks API: request failed.', [
                'method'      => $method,
                'url'         => $url,
                'status_code' => $statusCode,
                'error'       => $e->getMessage(),
            ]);

            if ($statusCode === 401 || $statusCode === 403) {
                throw new AuthenticationException('QuickBooks authentication failed.', $statusCode, $e);
            }

            if ($statusCode === 429) {
                throw new RateLimitException('QuickBooks rate limit exceeded.', $statusCode, $e);
            }

            throw new ApiException('QuickBooks API request failed: ' . $e->getMessage(), $statusCode, $e);
        }

        $body    = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->logger->debug('QuickBooks API: request successful.', [
            'method'      => $method,
            'status_code' => $response->getStatusCode(),
        ]);

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTokens(): array
    {
        return $this->ensureFreshToken();
    }

    public function getCompanyId(): string
    {
        return $this->qbCompanyId;
    }

    public function getRealmId(): string
    {
        return $this->realmId;
    }

    protected function ensureFreshToken(): array
    {
        if ($this->currentTokens !== null) {
            return $this->currentTokens;
        }

        $tokens = $this->tokenStore->get($this->qbCompanyId);

        if (!$tokens) {
            throw new AuthenticationException('No tokens found for this company.');
        }

        $accessExpiresAt  = $this->parseDate($tokens['access_token_expires_at'] ?? null);
        $refreshExpiresAt = $this->parseDate($tokens['refresh_token_expires_at'] ?? null);

        if ($accessExpiresAt && $accessExpiresAt->isPast()) {
            if ($refreshExpiresAt && $refreshExpiresAt->isPast()) {
                throw new AuthenticationException('Refresh token expired. Re-authorize required.');
            }

            $refreshToken = $tokens['refresh_token'] ?? '';

            if ($refreshToken === '') {
                throw new AuthenticationException('Refresh token is missing. Re-authorize required.');
            }

            $this->logger->info('QuickBooks Client: access token expired, refreshing.', [
                'qb_company_id' => $this->qbCompanyId,
            ]);

            $refreshed = $this->oauth->refreshToken($refreshToken, $this->realmId);
            $this->tokenStore->put($this->qbCompanyId, $refreshed);
            $tokens = array_merge($tokens, $refreshed);

            // Invalidate cached tokens so the refreshed set is used going forward.
            $this->currentTokens = null;
        }

        $this->currentTokens = $tokens;

        return $tokens;
    }

    protected function buildUrl(string $uri): string
    {
        $base = config('quickbooks.api_base.' . $this->environment);
        $base = str_replace('{realmId}', $this->realmId, $base);

        return rtrim($base, '/') . '/' . ltrim($uri, '/');
    }

    protected function client(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = $this->buildClient();
        }

        return $this->httpClient;
    }

    private function buildClient(): Client
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::retry(
            function (int $retries, RequestInterface $req, ?ResponseInterface $res, ?\Throwable $e): bool {
                $maxRetries = (int) config('quickbooks.retry_times', 3);

                if ($retries >= $maxRetries) {
                    return false;
                }

                if ($res !== null && in_array($res->getStatusCode(), [429, 500, 502, 503, 504], true)) {
                    $this->logger->warning('QuickBooks Client: retrying request.', [
                        'attempt'     => $retries + 1,
                        'status_code' => $res->getStatusCode(),
                    ]);
                    return true;
                }

                if ($e instanceof ConnectException) {
                    $this->logger->warning('QuickBooks Client: retrying after connection error.', [
                        'attempt' => $retries + 1,
                        'error'   => $e->getMessage(),
                    ]);
                    return true;
                }

                return false;
            },
            function (int $retries): int {
                return (int) (config('quickbooks.retry_sleep', 1000) * (2 ** ($retries - 1)));
            }
        ));

        return new Client([
            'timeout' => (int) config('quickbooks.timeout', 30),
            'handler' => $stack,
        ]);
    }
}
