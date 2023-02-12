<?php
/**
 *  * Created by mtils on 25.10.2022 at 15:14.
 **/

namespace Koansu\Routing;

use InvalidArgumentException;
use Koansu\Core\None;
use Koansu\Core\Stream;
use Koansu\Core\Url;
use Koansu\Http\HttpRequest;
use Koansu\Http\Psr\UploadedFile;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\Session;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

use function array_key_exists;
use function array_merge;
use function func_num_args;
use function is_array;

/**
 * @property-read array                           query
 * @property-read array                           bodyParams
 * @property-read array                           cookie
 * @property-read array                           server
 * @property-read UploadedFileInterface[]|array   files
 * @property-read Route|null                      matchedRoute
 * @property-read callable|null                   handler
 * @property-read array                           routeParameters
 * @property-read Url                             url
 * @property-read string                          method
 * @property-read string                          clientType
 * @property-read RouteScope                      routeScope
 * @property-read string                          locale
 * @property-read string                          determinedContentType
 * @property-read string                          apiVersion
 * @property-read object                          user
 * @property      Session                         session
 */
class HttpInput extends HttpRequest implements Input, ServerRequestInterface
{
    use InputTrait;

    protected $request = [
        Input::FROM_QUERY   => [],
        Input::FROM_BODY    => [],
        Input::FROM_COOKIE  => [],
        Input::FROM_SERVER  => [],
        Input::FROM_FILES   => []
    ];

    /**
     * @var Session
     */
    protected $session;

    public function __construct($dataOrUrl=[], array $headers=[], array $query=[], array $body=[], array $cookies=[], array $files=[], array $server=[], array $custom=[])
    {
        $dataIsUrl = $dataOrUrl instanceof Url;
        if ($this->isAssociative($dataOrUrl) && func_num_args() < 3) {
            parent::__construct($dataIsUrl ? [] : $dataOrUrl, $headers, $dataIsUrl ? $dataOrUrl : null);
            return;
        }
        $attributes = [
            'envelope'  => $headers,
            Input::FROM_QUERY => $query,
            Input::FROM_BODY => $body,
            Input::FROM_COOKIE => $cookies,
            Input::FROM_SERVER => $server,
            Input::FROM_FILES => $files,
            'custom' => $custom,
        ];

        if ($dataIsUrl) {
            $attributes['uri'] = $dataOrUrl;
            parent::__construct($attributes);
            return;
        }

        if (!is_array($dataOrUrl) || !isset($dataOrUrl['uri']) || !isset($dataOrUrl['method'])) {
            $attributes['payload'] = $dataOrUrl;
            parent::__construct($attributes);
            return;
        }
        $uri = $attributes['uri'] ?? null;
        parent::__construct([], $headers, $uri);
        $this->apply(array_merge($attributes, $dataOrUrl));
    }

    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->custom)) {
            return $this->custom[$key];
        }
        if (array_key_exists($key, $this->request[Input::FROM_QUERY])) {
            return $this->request[Input::FROM_QUERY][$key];
        }

        $body = $this->getParsedBody();
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $default;
    }

    /**
     * @param $offset
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetExists($offset): bool
    {
        if (isset($this->custom[$offset])) {
            return true;
        }
        if (isset($this->request[Input::FROM_QUERY][$offset])) {
            return true;
        }

        $body = $this->getParsedBody();
        return isset($body[$offset]);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        switch ($key) {
            case 'query':
                return $this->getQueryParams();
            case 'bodyParams':
                return $this->getParsedBody();
            case 'cookie':
                return $this->getCookieParams();
            case 'server':
                return $this->getServerParams();
            case 'files':
                return $this->getUploadedFiles();
            case 'custom':
                return $this->custom;
            case 'session':
                return $this->session;
            case 'user':
                return $this->getUser();
        }
        $value = $this->getInputTraitProperty($key);
        if (!$value instanceof None) {
            return $value;
        }
        return parent::__get($key);
    }

    /**
     * @return Url
     */
    public function getUrl(): Url
    {
        return $this->getUri();
    }

    /**
     * @return array
     */
    public function getServerParams() : array
    {
        return $this->request[Input::FROM_SERVER];
    }

    public function getCookieParams() : array
    {
        return $this->request[Input::FROM_COOKIE];
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function withCookieParams(array $cookies)
    {
        return $this->replicate([Input::FROM_COOKIE => $cookies]);
    }

    public function getQueryParams() : array
    {
        return $this->request[Input::FROM_QUERY];
    }

    public function withQueryParams(array $query) : HttpInput
    {
        $fork = $this->replicate();
        $fork->request[Input::FROM_QUERY] = $query;
        return $fork;
    }

    /**
     * @return UploadedFileInterface[]
     */
    public function getUploadedFiles()
    {
        return $this->request[Input::FROM_FILES];
    }

    /**
     * @param UploadedFileInterface[]|array $uploadedFiles
     * @return HttpInput
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $fork = $this->replicate();
        $fork->request[Input::FROM_FILES] = $this->castFiles($uploadedFiles);
        return $fork;
    }

    public function getParsedBody()
    {
        return $this->request[Input::FROM_BODY];
    }

    public function withParsedBody($data) : HttpInput
    {
        $fork = $this->replicate();
        $fork->request[Input::FROM_BODY] = $data;
        return $fork;
    }

    public function getAttributes() : array
    {
        return $this->custom;
    }

    public function getAttribute($name, $default = null)
    {
        if (array_key_exists($name, $this->custom)) {
            return $this->custom[$name];
        }
        return $default;
    }

    public function withAttribute($name, $value)
    {
        return $this->with($name, $value);
    }

    public function withoutAttribute($name)
    {
        return $this->without($name);
    }

    public function withUrl(Url $url) : HttpInput
    {
        return $this->replicate(['uri' => $url]);
    }

    public function withClientType(string $clientType) : HttpInput
    {
        return $this->replicate(['clientType' => $clientType]);
    }

    public function withApiVersion(string $version) : HttpInput
    {
        return $this->replicate(['apiVersion' => $version]);
    }

    public function withSession(Session $session)
    {
        return $this->replicate(['session' => $session]);
    }

    public function __toArray(): array
    {
        $all = $this->getAttributes();
        foreach ($this->getQueryParams() as $key=>$value) {
            if (!isset($all[$key])) {
                $all[$key] = $value;
            }
        }
        foreach ($this->getParsedBody() as $key=>$value) {
            if (!isset($all[$key])) {
                $all[$key] = $value;
            }
        }
        return $all;
    }

    protected function apply(array $attributes)
    {
        if (isset($attributes[Input::FROM_QUERY])) {
            $this->request[Input::FROM_QUERY] = $attributes[Input::FROM_QUERY];
        }
        if (isset($attributes[Input::FROM_BODY])) {
            $this->request[Input::FROM_BODY] = $attributes[Input::FROM_BODY];
        }
        if (isset($attributes[Input::FROM_COOKIE])) {
            $this->request[Input::FROM_COOKIE] = $attributes[Input::FROM_COOKIE];
        }
        if (isset($attributes[Input::FROM_SERVER])) {
            $this->request[Input::FROM_SERVER] = $attributes[Input::FROM_SERVER];
        }
        if (isset($attributes[Input::FROM_FILES])) {
            $this->applyFiles($attributes[Input::FROM_FILES]);
        }
        if (isset($attributes['session'])) {
            $this->session = $attributes['session'];
        }

        $this->applyInputTrait($attributes);
        parent::apply($attributes);
    }

    protected function copyStateInto(array &$attributes)
    {
        if (!isset($attributes[Input::FROM_QUERY])) {
            $attributes[Input::FROM_QUERY] = $this->request[Input::FROM_QUERY];
        }
        if (!isset($attributes[Input::FROM_BODY])) {
            $attributes[Input::FROM_BODY] = $this->request[Input::FROM_BODY];
        }
        if (!isset($attributes[Input::FROM_COOKIE])) {
            $attributes[Input::FROM_COOKIE] = $this->request[Input::FROM_COOKIE];
        }
        if (!isset($attributes[Input::FROM_SERVER])) {
            $attributes[Input::FROM_SERVER] = $this->request[Input::FROM_SERVER];
        }
        if (!isset($attributes[Input::FROM_FILES])) {
            $attributes[Input::FROM_FILES] = $this->request[Input::FROM_FILES];
        }
        if (!isset($attributes['session'])) {
            $attributes['session'] = $this->session;
        }
        $this->copyInputTraitStateInto($attributes);
        parent::copyStateInto($attributes);
    }

    protected function applyFiles(array $files)
    {
        $this->request[Input::FROM_FILES] = $this->castFiles($files);
    }

    protected function castFiles(array $files) : array
    {
        $formatted = [];
        foreach ($files as $key=>$value) {
            if ($value instanceof UploadedFileInterface) {
                $formatted[$key] = $value;
                continue;
            }
            if (!is_array($value)) {
                throw new InvalidArgumentException('Passed files have to be instanceof UploadedFileInterface or array');
            }
            if (!isset($value['tmp_name'])) {
                $formatted[$key] = $this->castFiles($value);
                continue;
            }
            if (is_string($value['tmp_name']) && $value['tmp_name']) {
                $formatted[$key] = $this->uploadedFile($value);
            }
            if (!is_array($value['tmp_name'])) {
                continue;
            }
            $formatted[$key] = [];
            foreach($this->reformatFiles($value) as $file) {
                $formatted[$key][] = $this->uploadedFile($file);
            }
        }

        return $formatted;
    }

    protected function reformatFiles(array $rawFiles) : array
    {
        $formatted = [];
        foreach ($rawFiles as $key=>$entries) {
            foreach ($entries as $index=>$value) {
                if (!isset($formatted[$index])) {
                    $formatted[$index] = [];
                }
                $formatted[$index][$key] = $value;
            }
        }
        return $formatted;
    }

    protected function uploadedFile(array $file) : UploadedFileInterface
    {
        if (!is_string($file['tmp_name'])) {
            throw new InvalidArgumentException('Unreadable file parameters');
        }
        return new UploadedFile(
            new Stream($file['tmp_name']),
            isset($file['size']) && is_int($file['size']) ? $file['size'] : -1,
            $file['error'],
            $file['name'] ?? null,
            $file['type'] ?? null
        );
    }

}