<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Auth;

use Givanov95\TypedHttp\Auth\BasicAuth;
use Givanov95\TypedHttp\Auth\BearerToken;
use Givanov95\TypedHttp\Auth\Certificate;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class AuthenticatorsTest extends TestCase
{
    public function test_basic_auth_sets_authorization_header(): void
    {
        $request = (new BasicAuth('user', 'pass'))->apply(new Request('GET', 'https://x.test'));

        $this->assertSame('Basic ' . base64_encode('user:pass'), $request->getHeaderLine('Authorization'));
    }

    public function test_bearer_token_sets_authorization_header(): void
    {
        $request = (new BearerToken('abc'))->apply(new Request('GET', 'https://x.test'));

        $this->assertSame('Bearer abc', $request->getHeaderLine('Authorization'));
    }

    public function test_certificate_exposes_client_options_and_headers(): void
    {
        $auth = new Certificate('/c.pem', '/k.pem', 'secret', '/ca.pem', ['x-api-key' => 'k']);

        $this->assertSame([
            'cert'    => '/c.pem',
            'ssl_key' => ['/k.pem', 'secret'],
            'verify'  => '/ca.pem',
        ], $auth->options());
        $this->assertSame('k', $auth->apply(new Request('GET', 'https://x.test'))->getHeaderLine('x-api-key'));
    }

    public function test_certificate_without_ca_bundle_does_not_override_verify(): void
    {
        $options = (new Certificate('/c.pem', '/k.pem'))->options();

        $this->assertArrayNotHasKey('verify', $options);
        $this->assertSame('/k.pem', $options['ssl_key']);
    }
}
