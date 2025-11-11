# TypedHttp

TypedHttp is a typed PHP HTTP request/response library designed to simplify building HTTP requests, parsing responses, and handling authentication.

## Features

- Abstract base Request class for typed HTTP requests  
- Extensible authentication mechanisms (BasicAuth, Certificate, etc.)  
- Support for various Content-Type and response formats  
- Clear and informative exception handling  
- Easily extendable for custom API integrations  

Request parameters are automatically gathered from the **public properties declared in your extending request class**. You simply declare the parameters as public properties in your class, and the library handles collecting and sending them with your request, keeping your code clean and simple.


## Installation

```bash
composer require givanov95/typed-http

use Givanov95\TypedHttp\Requests\Request;
use Givanov95\TypedHttp\Requests\Authorization\BasicAuth\BasicAuthAuthenticator;
use Givanov95\TypedHttp\Requests\Authorization\AuthenticatorInterface;

class MyRequest extends Request implements BasicAuthInterface
{
    // Required param for the request
    public string $param1 = null;

    // Optional param for the request
    public ?int $param2 = null;

    public function __construct(
        ?string $param1 = null,
        ?int $param2 = null
    )
    {
        parent::__construct();

        $this->param1 = $param1;
        $this->param2 = $param2;
    }

    public function getAuthenticator(): BasicAuthAuthenticator
    {
        return new BasicAuthAuthenticator('username', 'password');
    }

    public function getUrl(): Url
    {
        return new Url('https://api.example.com/data');
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

// Params are set via public properties declared in your extending Request class
$request = new MyRequest(param1: 'value1', param2: 42);
$response = $request->executeRequest();
$data = $request->getParsedBody();

print_r($data);
```
## Tests

Tests are not included yet but will be added in future releases to ensure quality and reliability.

## Examples

More example scripts and detailed usage scenarios will be added soon to help you get started quickly.

