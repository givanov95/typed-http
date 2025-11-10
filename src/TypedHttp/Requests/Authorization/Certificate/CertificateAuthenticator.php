<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Authorization\Certificate;

use Givanov95\TypedHttp\Requests\Authorization\AuthenticatorInterface;

final readonly class CertificateAuthenticator implements AuthenticatorInterface
{
    /**
     * @param string      $certificatePath Path to the public certificate (.pem)
     * @param string      $privateKeyPath  Path to the private key (.pem)
     * @param null|string $privateKeyPass  Optional private key password
     * @param null|string $caBundlePath    Optional CA bundle for verification
     * @param array       $defaultHeaders  additional headers like Accept, x-api-key, etc
     */
    public function __construct(
        private string $certificatePath,
        private string $privateKeyPath,
        private ?string $privateKeyPass = null,
        private ?string $caBundlePath = null,
        private array $defaultHeaders = [],
    ) {
    }

    /**
     * Returns authentication headers for the request.
     *
     * @return array<string, string>
     */
    public function getAuthHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Returns HTTP client options related to SSL/TLS.
     *
     * @return array<string, mixed>
     */
    public function getClientOptions(): array
    {
        return [
            'cert' => [$this->certificatePath, ''],
            'ssl_key' => [$this->privateKeyPath, $this->privateKeyPass ?? ''],
            'verify' => $this->caBundlePath,
        ];
    }
}
