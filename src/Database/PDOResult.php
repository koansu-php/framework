<?php
/**
 *  * Created by mtils on 31.12.2022 at 07:28.
 **/

namespace Koansu\Database;

use Generator;
use Koansu\Core\Contracts\Result;
use Koansu\Core\ResultTrait;
use PDOStatement;
use Traversable;

class PDOResult implements Result
{
    use ResultTrait;

    /**
     * @var PDOStatement
     **/
    protected $statement;

    /**
     * @param PDOStatement $statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }


    /**
     * @return Generator|Traversable
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        $this->statement->execute();
        while ($row = $this->statement->fetch()) {
            yield $row;
        }
    }
}