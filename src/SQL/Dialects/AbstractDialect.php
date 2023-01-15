<?php
/**
 *  * Created by mtils on 24.12.2022 at 10:39.
 **/

namespace Koansu\SQL\Dialects;

use DateTime;
use Koansu\Core\Type;
use Koansu\SQL\Contracts\Dialect;
use Koansu\SQL\SQLExpression;
use TypeError;

use function explode;
use function implode;
use function is_object;
use function is_scalar;
use function method_exists;
use function strpos;
use function strtoupper;

abstract class AbstractDialect implements Dialect
{
    protected $operatorMap = [
        '='     => '=',
        '!='    => '<>',
        '<>'    => '<>',
        '>'     => '>',
        '>='    => '>=',
        '<'     => '<',
        '<='    => '<=',
        'not'   => 'IS NOT',
        'is'    => 'IS',
        'like'  => 'LIKE',
        'in'    => 'IN'
    ];

    /**
     * Quote a string or table/column/database name
     *
     * @param string $string
     * @param string $type (default: string) Can be string|name
     *
     * @return string
     **/
    public function quote(string $string, string $type = Dialect::STR) : string
    {
        if ($type === 'string') {
            return $this->quoteString($string);
        }

        if (!strpos($string, '.')) {
            return $this->quoteName($string, $type);
        }

        $parts = explode('.', $string);
        $segments = [];

        foreach ($parts as $segment) {
            $segments[] = $this->quoteName($segment, $type);
        }

        return implode('.', $segments);
    }

    /**
     * @param string $string
     *
     * @return string
     */
    abstract protected function quoteString(string $string) : string;

    /**
     * @param string $name
     * @param string $type
     *
     * @return string
     */
    abstract protected function quoteName(string $name, string $type = 'name') : string;

    /**
     * @return string
     **/
    public function __toString() : string
    {
        return $this->name();
    }

    public function expression($atomic) : SQLExpression
    {
        if ($atomic === null) {
            return new SQLExpression('NULL');
        }

        if ($atomic instanceof DateTime) {
            return new SQLExpression('?', [$atomic->format($this->timeStampFormat())]);
        }

        if (is_scalar($atomic)) {
            return new SQLExpression('?', [$atomic]);
        }

        if (is_object($atomic) && method_exists($atomic, '__toString')) {
            return new SQLExpression($atomic);
        }

        throw new TypeError('No clue how to make this into an SQLExpression: ' . Type::of($atomic));
    }

    public function func(string $name, array $args = []): SQLExpression
    {
        return new SQLExpression(strtoupper($name), $args);
    }

}