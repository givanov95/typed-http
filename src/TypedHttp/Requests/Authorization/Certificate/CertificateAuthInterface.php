<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Authorization\Certificate;

interface CertificateAuthInterface
{
    /**
     * Returns an instance of the CertificateAuthenticator.
     */
    public function getAuthenticator(): CertificateAuthenticator;
}
