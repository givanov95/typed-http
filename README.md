# TypedHttp

TypedHttp is a typed PHP HTTP request/response library designed to simplify building HTTP requests, parsing responses, and handling authentication.

## Features

- Abstract base Request class for typed HTTP requests  
- Extensible authentication mechanisms (BasicAuth, Certificate, etc.)  
- Support for various Content-Type and response formats  
- Clear and informative exception handling  
- Easily extendable for custom API integrations  

## Installation

```bash
composer require givanov95/typed-http

use Givanov95\TypedHttp\Requests\Request;
use Givanov95\TypedHttp\Requests\Authorization\BasicAuth\BasicAuthAuthenticator;
use Givanov95\TypedHttp\Requests\Authorization\AuthenticatorInterface;

class MyRequest extends Request implements BasicAuthInterface
{
    private BasicAuthAuthenticator $authenticator;

    public function __construct()
    {
        parent::__construct();

        $this->authenticator = new BasicAuthAuthenticator('username', 'password');
    }

    public function getAuthenticator(): BasicAuthAuthenticator
    {
        return $this->authenticator;
    }

    public function getUrl(): Url
    {
        return new Url('https://api.example.com/data');
    }

    public function getRequestParams(): array
    {
        return ['param1' => 'value1'];
    }

    protected function getHttpMethodType(): HttpMethod
    {
        return HttpMethod::POST;
    }

    protected function getContentType(): ContentType
    {
        return ContentType::JSON;
    }

    protected function getExpectedResponseFormat(): ExpectedResponseFormat
    {
        return ExpectedResponseFormat::JSON;
    }
}

$request = new MyRequest();
$response = $request->executeRequest();
$data = $request->getParsedBody();

print_r($data);
```
## Tests

Tests are not included yet but will be added in future releases to ensure quality and reliability.

## Examples

More example scripts and detailed usage scenarios will be added soon to help you get started quickly.

