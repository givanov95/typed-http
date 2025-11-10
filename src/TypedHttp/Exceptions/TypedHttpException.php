<?php

declare(strict_types=1);

namespace TypedHttp\Exceptions;

use Exception;

class TypedHttpException extends Exception
{
    private ?string $context = null;

    private array $details = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Exception $previous = null,
        ?string $context = null,
        array $details = []
    ) {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
        $this->details = $details;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(string $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function setDetails(array $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
            'details' => $this->details,
            'previous' => $this->getPrevious() ? $this->getPrevious()->getMessage() : null,
        ];
    }
}
