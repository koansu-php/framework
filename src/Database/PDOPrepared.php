<?php
/**
 *  * Created by mtils on 31.12.2022 at 07:38.
 **/

namespace Koansu\Database;

use Koansu\Database\Contracts\Prepared;
use Koansu\Core\Str;
use Koansu\Core\ResultTrait;

use Exception;
use Koansu\SQL\SQL;
use PDO;
use PDOStatement;

use Traversable;

use function call_user_func;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;


class PDOPrepared extends Str implements Prepared
{
    use ResultTrait;

    /**
     * @var string
     **/
    protected $query;

    /**
     * @var array
     **/
    protected $bindings = [];

    /**
     * @var PDOStatement
     **/
    protected $statement;

    /**
     * @var bool
     **/
    protected $returnAffected = null;

    /**
     * @var callable
     **/
    protected $errorHandler;

    /**
     * Pass in a prepared statement without bindings. The bindings
     * will be applied by this object.
     *
     * @param PDOStatement  $statement
     * @param string|Str    $query
     * @param bool          $returnAffected (default:true)
     * @param callable|null      $errorHandler (optional)
     **/
    public function __construct(PDOStatement $statement, $query, bool $returnAffected=true, callable $errorHandler=null)
    {
        parent::__construct();
        $this->statement = $statement;
        $this->returnAffected = $returnAffected;
        $this->query = $query;
        $this->errorHandler = $errorHandler;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function query()
    {
        return $this->query;
    }

    public function bind(array $bindings) : Prepared
    {
        $this->bindings = $bindings;
        return $this;
    }

    public function write(array $bindings=null, bool $returnAffectedRows=null) : ?int
    {

        $returnAffectedRows = $returnAffectedRows !== null ? $returnAffectedRows : $this->returnAffected;

        $bindings = is_array($bindings) ? $bindings : $this->bindings;

        $this->bindAndRun($bindings);

        if (!$returnAffectedRows) {
            return null;
        }

        return $this->statement->rowCount();
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function getIterator() : Traversable
    {

        $this->bindAndRun($this->bindings);

        while ($row = $this->statement->fetch()) {
            yield $row;
        }
    }

    protected function bindAndRun(array $bindings) : ?bool
    {
        try {
            static::bindToStatement($this->statement, $bindings);
            return $this->statement->execute();
        } catch (Exception $e) {
            //
        }

        if ($this->errorHandler) {
            call_user_func($this->errorHandler, $e, $this->query);
        }

        return null;
    }

    /**
     * Add bindings to a statement. Cast integers, null, bool and string.
     *
     * @param PDOStatement $statement
     * @param array $bindings
     **/
    public static function bindToStatement(PDOStatement $statement, array $bindings=[]) : void
    {
        foreach ($bindings as $key=>$value) {

            $casted = is_bool($value) ? (int)$value : $value;

            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $casted,
                is_int($casted) ? PDO::PARAM_INT : ($casted === null ? PDO::PARAM_NULL : PDO::PARAM_STR)
            );

        }
    }

    public function __toString() : string
    {
        return SQL::render($this->query, $this->bindings);
    }
}
