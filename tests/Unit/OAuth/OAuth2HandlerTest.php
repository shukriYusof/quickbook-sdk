<?php

namespace QuickBooks\SDK\Tests\Unit\OAuth;

use Psr\Log\NullLogger;
use QuickBooks\SDK\Exceptions\AuthenticationException;
use QuickBooks\SDK\OAuth\OAuth2Handler;
use QuickBooks\SDK\Tests\TestCase;

class OAuth2HandlerTest extends TestCase
{
    private OAuth2Handler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new OAuth2Handler(new NullLogger());
    }

    // -------------------------------------------------------------------------
    // getAuthorizationUrl
    // -------------------------------------------------------------------------

    public function test_authorization_url_contains_required_params(): void
    {
        $url = $this->handler->getAuthorizationUrl('company-uuid-abc');

        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('state=', $url);
        $this->assertStringContainsString('scope=', $url);
    }

    public function test_authorization_url_embeds_company_id_in_state(): void
    {
        $url = $this->handler->getAuthorizationUrl('company-uuid-abc');

        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $data = $this->handler->decodeState($params['state']);

        $this->assertEquals('company-uuid-abc', $data['qb_company_id']);
    }

    // -------------------------------------------------------------------------
    // State encoding / decoding
    // -------------------------------------------------------------------------

    public function test_state_round_trip_preserves_all_fields(): void
    {
        $url = $this->handler->getAuthorizationUrl('company-xyz');

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $decoded = $this->handler->decodeState($params['state']);

        $this->assertArrayHasKey('qb_company_id', $decoded);
        $this->assertArrayHasKey('nonce', $decoded);
        $this->assertArrayHasKey('ts', $decoded);
        $this->assertEquals('company-xyz', $decoded['qb_company_id']);
        $this->assertIsInt($decoded['ts']);
    }

    public function test_decode_state_throws_on_tampered_signature(): void
    {
        $url = $this->handler->getAuthorizationUrl('company-abc');
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        // Replace the HMAC signature with garbage.
        [$payload] = explode('.', $params['state']);
        $tampered  = $payload . '.invalidsignatureXXXXXXXX';

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OAuth state signature');

        $this->handler->decodeState($tampered);
    }

    public function test_decode_state_throws_when_no_dot_separator(): void
    {
        $this->expectException(AuthenticationException::class);

        // Valid-looking base64 but no "." separator means no signature part.
        $this->handler->decodeState('aGVsbG93b3JsZA');
    }

    public function test_decode_state_throws_on_malformed_json(): void
    {
        // Build a state where the payload decodes to non-JSON, but HMAC matches.
        $payload  = rtrim(strtr(base64_encode('not-valid-json'), '+/', '-_'), '=');
        $sig      = hash_hmac('sha256', $payload, 'test-client-secret-32chars-min!!');
        $state    = $payload . '.' . $sig;

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OAuth state');

        $this->handler->decodeState($state);
    }

    public function test_different_companies_produce_different_states(): void
    {
        $url1 = $this->handler->getAuthorizationUrl('company-aaa');
        $url2 = $this->handler->getAuthorizationUrl('company-bbb');

        parse_str(parse_url($url1, PHP_URL_QUERY), $p1);
        parse_str(parse_url($url2, PHP_URL_QUERY), $p2);

        $this->assertNotEquals($p1['state'], $p2['state']);
    }

    // -------------------------------------------------------------------------
    // extractCompanyId
    // -------------------------------------------------------------------------

    public function test_extract_company_id_returns_correct_value(): void
    {
        $url = $this->handler->getAuthorizationUrl('expected-company-id');
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $id = $this->handler->extractCompanyId($params['state']);

        $this->assertEquals('expected-company-id', $id);
    }

    public function test_extract_company_id_throws_when_field_missing(): void
    {
        // Build a valid signed state that is missing qb_company_id.
        $payload = rtrim(strtr(base64_encode((string) json_encode(['nonce' => 'abc', 'ts' => time()])), '+/', '-_'), '=');
        $sig     = hash_hmac('sha256', $payload, 'test-client-secret-32chars-min!!');
        $state   = $payload . '.' . $sig;

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing company ID');

        $this->handler->extractCompanyId($state);
    }
}
