<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests;

class Options
{
    /**
     * @var null|bool
     */
    public ?bool $returnAssociativeResponse = false;

    /**
     * Options constructor to initialize any properties if needed.
     *
     * @param null|bool $associativeResponse
     * @param ?bool     $returnAssociativeResponse
     */
    public function __construct(
        ?bool $returnAssociativeResponse = false,
    ) {
        $this->returnAssociativeResponse = $returnAssociativeResponse;
    }

    /**
     * Optionally convert to an array for easier access/serialization.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'returnAssociativeResponse' => $this->returnAssociativeResponse,
        ];
    }

    /**
     * Get the value of associativeResponse.
     *
     * @return ?bool
     */
    public function getReturnAssociativeResponse(): ?bool
    {
        return $this->returnAssociativeResponse;
    }

    /**
     * Set the value of associativeResponse.
     *
     * @param  ?bool $associativeResponse
     * @return self
     */
    public function setReturnAssociativeResponse(?bool $associativeResponse): self
    {
        $this->returnAssociativeResponse = $associativeResponse;

        return $this;
    }
}
