<?php
/**
 *  * Created by mtils on 24.12.2022 at 10:56.
 **/

namespace Koansu\SQL\Dialects;

use InvalidArgumentException;

use function str_replace;

/**
 * Class MySQLDialect
 *
 * CAUTION: This class is not complete. No exception conversion or other special
 * things are implemented here.
 *
 * @package Ems\Model\Database\Dialects
 */
class MySQLDialect extends AbstractDialect
{

    /**
     * {@inheritdoc}
     *
     * @return string
     **/
    public function name() : string
    {
        return 'mysql';
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

    /**
     * @param string $string
     *
     * @return string
     */
    protected function quoteString(string $string) : string
    {
        $search = ["\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a"];
        $replace = ["\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z"];

        return '"' . str_replace($search, $replace, $string) . '"';
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
        return '`' . str_replace('`', '``', $name) . '`';
    }

}