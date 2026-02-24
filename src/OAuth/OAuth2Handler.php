<?php

namespace QuickBooks\SDK\OAuth;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use QuickBooks\SDK\Exceptions\AuthenticationException;

class OAuth2Handler
{
    private ?Client $httpClient = null;

    public function __construct(private LoggerInterface $logger = new NullLogger()) {}

    public function getAuthorizationUrl(string $qbCompanyId, ?array $scopes = null, ?string $state = null, array $extraParams = []): string
    {
        $state = $state ?: $this->encodeState([
            'qb_company_id' => $qbCompanyId,
            'nonce'         => Str::random(20),
            'ts'            => time(),
        ]);

        $scopes       = $scopes ?: config('quickbooks.oauth.scopes', []);
        $authorizeUrl = config('quickbooks.oauth.authorize_url');

        $query = http_build_query(array_merge([
            'client_id'     => config('quickbooks.client_id'),
            'response_type' => 'code',
            'scope'         => implode(' ', $scopes),
            'redirect_uri'  => config('quickbooks.redirect_uri'),
            'state'         => $state,
        ], $extraParams));

        return rtrim($authorizeUrl, '?') . '?' . $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, string $realmId): array
    {
        $this->logger->info('QuickBooks OAuth: exchanging authorization code.', ['realm_id' => $realmId]);

        $data = $this->tokenRequest([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => config('quickbooks.redirect_uri'),
        ]);

        $tokens = $this->normalizeTokenResponse($data, $realmId);

        $this->logger->info('QuickBooks OAuth: token exchange successful.', ['realm_id' => $realmId]);

        return $tokens;
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshToken(string $refreshToken, ?string $realmId = null): array
    {
        $this->logger->info('QuickBooks OAuth: refreshing access token.', ['realm_id' => $realmId]);

        $data = $this->tokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $tokens = $this->normalizeTokenResponse($data, $realmId);

        $this->logger->info('QuickBooks OAuth: token refresh successful.', ['realm_id' => $realmId]);

        return $tokens;
    }

    public function revokeToken(string $refreshToken): bool
    {
        $this->logger->info('QuickBooks OAuth: revoking token.');

        $url = config('quickbooks.oauth.revoke_url');

        try {
            $response = $this->client()->post($url, [
                'auth'    => [config('quickbooks.client_id'), config('quickbooks.client_secret')],
                'headers' => ['Accept' => 'application/json'],
                'json'    => ['token' => $refreshToken],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('QuickBooks OAuth: failed to revoke token.', ['error' => $e->getMessage()]);
            throw new AuthenticationException('Failed to revoke token: ' . $e->getMessage(), 0, $e);
        }

        $success = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

        $this->logger->info('QuickBooks OAuth: token revocation completed.', ['success' => $success]);

        return $success;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeState(string $state): array
    {
        $parts   = explode('.', $state, 2);
        $encoded = $parts[0];
        $sig     = $parts[1] ?? '';

        $expected = hash_hmac('sha256', $encoded, (string) config('quickbooks.client_secret'));

        if (!hash_equals($expected, $sig)) {
            throw new AuthenticationException('Invalid OAuth state signature.');
        }

        $data = json_decode((string) base64_decode(strtr($encoded, '-_', '+/')), true);

        if (!is_array($data)) {
            throw new AuthenticationException('Invalid OAuth state.');
        }

        return $data;
    }

    public function extractCompanyId(string $state): string
    {
        $data        = $this->decodeState($state);
        $qbCompanyId = Arr::get($data, 'qb_company_id');

        if (!$qbCompanyId) {
            throw new AuthenticationException('Missing company ID in OAuth state.');
        }

        return (string) $qbCompanyId;
    }

    protected function encodeState(array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
        $sig     = hash_hmac('sha256', $encoded, (string) config('quickbooks.client_secret'));

        return $encoded . '.' . $sig;
    }

    /**
     * @return array<string, mixed>
     */
    protected function tokenRequest(array $params): array
    {
        $url = config('quickbooks.oauth.token_url');

        try {
            $response = $this->client()->post($url, [
                'auth'        => [config('quickbooks.client_id'), config('quickbooks.client_secret')],
                'form_params' => $params,
                'headers'     => ['Accept' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('QuickBooks OAuth: token request failed.', ['error' => $e->getMessage()]);
            throw new AuthenticationException('OAuth token request failed: ' . $e->getMessage(), 0, $e);
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (!is_array($payload)) {
            throw new AuthenticationException('Invalid OAuth token response.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeTokenResponse(array $payload, ?string $realmId): array
    {
        $now = Carbon::now();

        $accessToken  = Arr::get($payload, 'access_token');
        $refreshToken = Arr::get($payload, 'refresh_token');

        if (!$accessToken || !$refreshToken) {
            throw new AuthenticationException('OAuth response missing access or refresh token.');
        }

        $accessExpiresIn  = (int) Arr::get($payload, 'expires_in', 0);
        $refreshExpiresIn = (int) Arr::get($payload, 'x_refresh_token_expires_in', 0);

        return [
            'access_token'             => $accessToken,
            'refresh_token'            => $refreshToken,
            'access_token_expires_at'  => $accessExpiresIn > 0 ? $now->copy()->addSeconds($accessExpiresIn) : null,
            'refresh_token_expires_at' => $refreshExpiresIn > 0 ? $now->copy()->addSeconds($refreshExpiresIn) : null,
            'realm_id'                 => $realmId,
        ];
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
                    $this->logger->warning('QuickBooks OAuth: retrying request.', [
                        'attempt'     => $retries + 1,
                        'status_code' => $res->getStatusCode(),
                    ]);
                    return true;
                }

                if ($e instanceof ConnectException) {
                    $this->logger->warning('QuickBooks OAuth: retrying after connection error.', [
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
