<?php
/**
 *  * Created by mtils on 19.02.2023 at 08:05.
 **/

namespace Koansu\Routing\Middleware;

use Koansu\Core\Response;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\HttpInput;

use function in_array;
use function strtolower;
use function strtoupper;

class MethodOverwriteMiddleware
{
    private $allowInHeader = true;

    private $allowInParameters = true;

    private $headerName = 'X-HTTP-Method-Override';

    private $parameterName = '_method';

    public function __invoke(Input $input, callable $next) : Response
    {
        if (!$input instanceof HttpInput || $input->getMethod() != 'POST') {
            return $next($input);
        }

        if ($method = $this->getFromHeader($input)) {
            return $next($input->withMethod($method));
        }

        if ($method = $this->getFromParameters($input)) {
            return $next($input->withMethod($method));
        }

        return $next($input);
    }

    public function isAllowedInHeader() : bool
    {
        return $this->allowInHeader;
    }

    public function allowInHeader(bool $allow=true) : MethodOverwriteMiddleware
    {
        $this->allowInHeader = $allow;
        return $this;
    }

    public function isAllowedInParameters() : bool
    {
        return $this->allowInParameters;
    }

    public function allowInParameters(bool $allow=true) : MethodOverwriteMiddleware
    {
        $this->allowInParameters = $allow;
        return $this;
    }

    protected function getFromHeader(HttpInput $input) : string
    {
        if (!$this->allowInHeader) {
            return '';
        }
        if ($method = $input->getHeaderLine($this->headerName)) {
            return $this->returnIfValid($method);
        }
        return '';
    }

    protected function getFromParameters(HttpInput $input) : string
    {
        if (!$this->allowInParameters) {
            return '';
        }
        if ($method = $input->get($this->parameterName)) {
            return $this->returnIfValid($method);
        }
        if ($method = $input->get(strtolower($this->headerName))) {
            return $this->returnIfValid($method);
        }
        return '';
    }

    protected function returnIfValid(string $method) : string
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'PATCH', 'PURGE', 'TRACE'], true)) {
            return '';
        }
        return $method;
    }
}