<?php
/**
 *  * Created by mtils on 24.12.2022 at 06:28.
 **/

namespace Koansu\Database;

use Throwable;

use function property_exists;
use function strpos;

/**
 * This class contains information to estimate an error.
 **/
class NativeError
{

    /**
     * Return the executed query.
     *
     * @var string
     **/
    public $query = '';

    /**
     * The ANSI SQL 92 error code.
     *
     * @var string
     **/
    public $sqlState = 'HY000';

    /**
     * The native error code of the database backend.
     *
     * @var string|integer
     **/
    public $code = 0;

    /**
     * The native error message of the database backend.
     *
     * @var string
     **/
    public $message = '';

    /**
     * @var string[]
     */
    public static $lostConnectionNeedles = [
        'server has gone away',
        'no connection to the server',
        'Lost connection',
        'is dead or not enabled',
        'Error while sending',
        'decryption failed or bad record mac',
        'server closed the connection unexpectedly',
        'SSL connection has been closed unexpectedly',
        'Error writing data to the connection',
        'Resource deadlock avoided',
        'Transaction() on null',
    ];

    /**
     * Fill the object.
     *
     * @param array $error (optional)
     **/
    public function __construct(array $error = [])
    {
        foreach ($error as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * @param Throwable|NativeError $error
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function isLostConnectionError($error) : bool
    {
        $message = self::extractMessage($error);
        foreach (self::$lostConnectionNeedles as $needle) {
            if (strpos($message, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Throwable|NativeError $error
     *
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    private static function extractMessage($error) : string
    {
        return $error instanceof Throwable ? $error->getMessage() : "$error";
    }
}