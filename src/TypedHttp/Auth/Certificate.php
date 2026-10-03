<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Auth;

use Givanov95\TypedHttp\Contracts\Authenticator;
use Psr\Http\Message\RequestInterface;

/**
 * Mutual TLS: the client certificate travels as transport options, optional headers are added on top.
 */
final readonly class Certificate implements Authenticator
{
    /**
     * @param string               $certificatePath Public certificate (.pem)
     * @param string               $privateKeyPath  Private key (.pem)
     * @param null|string          $privateKeyPass  Private key password
     * @param null|string          $caBundlePath    CA bundle used to verify the server
     * @param array<string,string> $headers         Extra headers (x-api-key, ...)
     */
    public function __construct(
        private string $certificatePath,
        private string $privateKeyPath,
        private ?string $privateKeyPass = null,
        private ?string $caBundlePath = null,
        private array $headers = [],
    ) {
    }

    public function apply(RequestInterface $request): RequestInterface
    {
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    public function options(): array
    {
        $options = [
            'cert'    => $this->certificatePath,
            'ssl_key' => $this->privateKeyPass !== null ? [$this->privateKeyPath, $this->privateKeyPass] : $this->privateKeyPath,
        ];

        if ($this->caBundlePath !== null) {
            $options['verify'] = $this->caBundlePath;
        }

        return $options;
    }
}
