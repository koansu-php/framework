<?php
/**
 *  * Created by mtils on 08.10.2022 at 07:55.
 **/

namespace Koansu\Config\Exception;


use RuntimeException;
use Throwable;

/**
 * Class EnvFileException
 *
 * Mark an error in the env file. Pass a line to help the developer
 * @package Ems\Config\Exception
 */
class EnvFileException extends RuntimeException
{
    /**
     * @var int
     */
    protected $envFileLine = 0;

    public function __construct(
        $envFileLine = 0,
        $message = '',
        Throwable $previous = null
    ) {
        if (!$message && $previous) {
            $message = "Error in line $envFileLine: " . $previous->getMessage();
        }
        parent::__construct($message, 0, $previous);
        $this->envFileLine = $envFileLine;
    }

    /**
     * @return int
     */
    public function getEnvFileLine() : int
    {
        return $this->envFileLine;
    }
}