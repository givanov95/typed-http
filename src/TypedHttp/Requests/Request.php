<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests;

use Givanov95\TypedHttp\Requests\Authorization\AuthenticatorInterface;
use Givanov95\TypedHttp\Requests\Enums\ContentType;
use Givanov95\TypedHttp\Requests\Enums\ExpectedResponseFormat;
use Givanov95\TypedHttp\Requests\Enums\HttpMethod;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use TypedHttp\ResponseParser;

abstract class Request
{
    public StreamInterface $responseBody;

    /**
     * The HTTP client used for making requests.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Request url.
     *
     * @return Url
     */
    abstract public function getUrl(): Url;

    /**
     * Request authenticator.
     *
     * @return ?AuthenticatorInterface
     */
    abstract public function getAuthenticator(): ?AuthenticatorInterface;

    /**
     * Determine if the request should be JSON or form-data.
     *
     * @return ContentType
     */
    abstract protected function getContentType(): ContentType;

    /**
     * Determine if the request should be JSON or form-data.
     *
     * @return ExpectedResponseFormat
     */
    abstract protected function getExpectedResponseFormat(): ExpectedResponseFormat;

    /**
     * Request method.
     *
     * @return HttpMethod
     */
    abstract protected function getHttpMethodType(): HttpMethod;

    /**
     * @var Options
     */
    protected Options $options;

    /**
     * @var bool
     */
    protected bool $executedRequest = false;

    /**
     * @param  ?Options         $options
     * @throws RuntimeException if authentication credentials are invalid, token retrieval fails, or the UPS API base URL is not set
     */
    public function __construct(?Options $options = null)
    {
        $this->client = new Client();
        $this->options = $options ?? new Options();
        $this->executedRequest = false;
    }

    private function getRequestParams()
    {
        $reflection = new \ReflectionObject($this);
        $props = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($props as $prop) {
            // Only include properties declared in the child class (not inherited)
            if ($prop->getDeclaringClass()->getName() === get_class($this)) {
                $name = $prop->getName();
                $params[$name] = $this->$name;
            }
        }

        return array_filter(
            $params,
            fn ($value) => $value !== null
        );
    }

    public function executeRequest()
    {
        try {
            $request = $this->getClientRequest();
            $response = $this->client
                ->sendAsync($request)
                ->wait();

            if ($response->getStatusCode() >= 400) {
                throw new RuntimeException('HTTP error: ' . $response->getStatusCode());
            }

            $responseBody = $response->getBody();
            $this->setResponseBody($responseBody);
            $this->executedRequest = true;

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException('Request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getClientRequest(): Psr7Request
    {
        $method = $this->getHttpMethodType();
        $url = $this->getUrl()->toString();
        $requestParams = $this->getRequestParams();
        $contentType = $this->getContentType();
        $authHeaders = $this->getAuthenticator()?->getAuthHeaders() ?? [];

        $headers = [
            ...$this->getHeaders(),
            ...$authHeaders,
            'Accept' => $this->getExpectedResponseFormat()->value,
        ];


        $encodedParams = $contentType->encodeBody($requestParams);

        if ($method === HttpMethod::GET) {
            if ($encodedParams !== '') {
                $url .= '?' . $encodedParams;
            }

            return new Psr7Request(
                $method->value,
                $url,
                $headers
            );
        }
        $headers['Content-Type'] = $contentType->value;

        return new Psr7Request(
            $method->value,
            $url,
            $headers,
            $encodedParams
        );
    }

    public function getHeaders(): array
    {
        return [];
    }

    /**
     * Get the value of options.
     *
     * @return Options
     */
    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * Set the value of options.
     *
     * @param  Options $options
     * @return self
     */
    public function setOptions(Options $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  null|bool    $associative - When TRUE, returned objects will be converted into associative arrays
     * @return object|array
     */
    public function getParsedBody(?bool $associative = false)
    {
        if (! $this->executedRequest) {
            $this->executeRequest();
        }

        return ResponseParser::parse(
            $this->getResponseBody(),
            $this->getExpectedResponseFormat()->value,
            $associative
        );
    }

    /**
     * Get the value of response.
     *
     * @return StreamInterface
     */
    public function getResponseBody(): StreamInterface
    {
        return $this->responseBody;
    }

    /**
     * Set the value of response.
     *
     * @param  StreamInterface $response
     * @param  StreamInterface $responseBody
     * @return self
     */
    public function setResponseBody(StreamInterface $responseBody): self
    {
        $this->responseBody = $responseBody;

        return $this;
    }
}
