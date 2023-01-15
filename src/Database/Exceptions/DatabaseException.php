<?php
/**
 *  * Created by mtils on 24.12.2022 at 06:34.
 **/

namespace Koansu\Database\Exceptions;

use Koansu\Database\NativeError;
use RuntimeException;
use Throwable;

use function substr;

class DatabaseException extends RuntimeException
{
    /**
     * @var string
     **/
    protected $query = '';

    /**
     * @var string
     **/
    protected $sqlState = 'HY000';

    /**
     * @var int|string
     **/
    protected $nativeCode = 0;

    /**
     * @var string
     **/
    protected $nativeMessage = '';

    /**
     * @var NativeError
     **/
    protected $nativeError;

    /**
     * @param string                $message (optional)
     * @param string|NativeError    $queryOrError   (optional)
     * @param int                   $code    (optional)
     * @param ?Throwable            $previous (optional)
     **/
    public function __construct(string $message='', $queryOrError='', int $code=0, Throwable $previous=null)
    {

        $this->nativeError = $queryOrError instanceof NativeError ?
            $queryOrError :
            new NativeError(['query' => $queryOrError]);

        parent::__construct($this->buildMessage($message, $this->query()), $code, $previous);

    }

    /**
     * Return the sql query string
     *
     * @return string
     **/
    public function query() : string
    {
        return $this->nativeError->query;
    }

    /**
     * Return PDO state
     *
     * @return string (default: HY000)
     **/
    public function sqlState() : string
    {
        return $this->nativeError->sqlState;
    }

    /**
     * Return the dbms error code
     *
     * @return string|int
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function nativeCode()
    {
        return $this->nativeError->code;
    }

    /**
     * Return the dbms error message
     *
     * @return string
     **/
    public function nativeMessage() : string
    {
        return $this->nativeError->message;
    }

    public function nativeError() : NativeError
    {
        return $this->nativeError;
    }

    /**
     * Fill the exception by an array. The following keys are supported:
     * sqlstate, code, msg
     *
     * @param array $error
     *
     * @return self
     **/
    public function fill(array $error) : DatabaseException
    {
        $this->setSqlState($error['sqlstate'] ?? 'HY000')
            ->setNativeCode($error['code'] ?? 0)
            ->setNativeMessage($error['msg'] ?? '');

        return $this;

    }

    /**
     * Appends the $query to the message and formats it for parent.
     *
     * @param string $message
     * @param string $query
     *
     * @return string
     **/
    protected function buildMessage(string $message, string $query) : string
    {
        if (!$query) {
            return $message;
        }

        if (!$message) {
            return substr("Error in QUERY: $query", 0, 1024);
        }

        return substr("$message (QUERY: $query)", 0, 1024);
    }

}