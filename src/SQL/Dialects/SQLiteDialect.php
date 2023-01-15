<?php
/**
 *  * Created by mtils on 24.12.2022 at 10:57.
 **/

namespace Koansu\SQL\Dialects;

use InvalidArgumentException;

use Koansu\SQL\SQLExpression;

use function str_repeat;
use function str_replace;
use function str_split;
use function strtolower;


class SQLiteDialect extends AbstractDialect
{

    /**
     * {@inheritdoc}
     *
     * @return string
     **/
    public function name() : string
    {
        return 'sqlite';
    }

    /**
     * Return the timestamp format of this database.
     *
     * @return string
     **/
    public function timeStampFormat() : string
    {
        return 'Y-m-d H:i:s';
    }

    public function func(string $name, array $args = []): SQLExpression
    {
        if (strtolower($name) == 'concat') {
            return new SQLExpression(
                implode(' || ' , str_split(str_repeat('?', count($args)))),
            $args);
        }
        return parent::func($name, $args);
    }


    /**
     * @param string $string
     *
     * @return string
     */
    protected function quoteString(string $string) : string
    {
        return "'" . str_replace("'", "''", $string) . "'";
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @return string
     */
    protected function quoteName(string $name, string $type = 'name') : string
    {
        if ($type != 'name') {
            throw new InvalidArgumentException("type has to be either string|name, not $type");
        }
        return '"' . str_replace('"', '""', $name) . '"';
    }

}