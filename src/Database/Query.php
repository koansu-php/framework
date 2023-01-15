<?php


/**
 *  * Created by mtils on 24.12.2022 at 13:22.
 **/

namespace Koansu\Database;



use Exception;
use Koansu\Core\Contracts\Paginatable;
use Koansu\Core\Contracts\Result;
use Koansu\Core\Contracts\SelfRenderable;
use Koansu\Core\SelfRenderableTrait;
use Koansu\Core\Str;
use Koansu\Database\Contracts\DatabaseConnection;
use Koansu\Pagination\Paginator;
use Koansu\Core\ResultTrait;

use Koansu\SQL\Query as BaseQuery;

use Koansu\SQL\SQL;
use Koansu\SQL\SQLExpression;

use Traversable;

use function call_user_func;
use function class_exists;
use function is_bool;

class Query extends BaseQuery implements Result, Paginatable, SelfRenderable
{
    use ResultTrait;
    use SelfRenderableTrait;

    /**
     * @var DatabaseConnection
     */
    private $connection;

    /**
     * @var BaseQuery
     */
    private $countQuery;

    /**
     * @var callable
     */
    private $paginatorFactory;

    /**
     * This is just for tests.
     *
     * @var boolean
     */
    public static $paginatorClassExists;

    /**
     * Retrieve the results from database...
     *
     * @return Traversable
     * @throws Exception
     */
    public function getIterator() : Traversable
    {
        $this->operation = 'SELECT';
        return $this->readFromConnection($this)->getIterator();
    }

    /**
     * Paginate the result. Return whatever paginator you use.
     * The paginator should be \Traversable.
     *
     * @param int $page (optional)
     * @param int $perPage (optional)
     *
     * @return iterable|Paginator
     * @noinspection PhpDocSignatureInspection
     */
    public function paginate(int $page = 1, int $perPage = 15) : iterable
    {

        $result = $this->runPaginated($page, $perPage);

        if ($this->paginatorFactory) {
            return call_user_func($this->paginatorFactory, $result, $this, $page, $perPage);
        }

        if (!$this->paginatorClassExists()) {
            return $result;
        }

        $paginator = new Paginator($page, $perPage);
        return $paginator->setResult($result, $this->getTotalCount());
    }

    /**
     * Perform an INSERT query on the assigned connection.
     *
     * @param array $values (optional, otherwise use $this->values)
     * @param bool  $returnLastInsert (default:true)
     *
     * @return int
     */
    public function insert(array $values = [], bool $returnLastInsert = true) : int
    {
        return $this->writeToConnection('INSERT', $returnLastInsert, $values);
    }

    /**
     * Perform a REPLACE INTO | INSERT ON DUPLICATE KEY UPDATE query.
     *
     * @param array $values (optional, otherwise use $this->values)
     * @param bool  $returnAffected (default:true)
     *
     * @return int
     */
    public function replace(array $values = [], bool $returnAffected = true) : int
    {
        return $this->writeToConnection('REPLACE', $returnAffected, $values);
    }

    /**
     * Perform an update query.
     *
     * @param array $values (optional, otherwise use $this->values)
     * @param bool $returnAffected (default:true)
     *
     * @return int
     */
    public function update(array $values = [], bool $returnAffected = true) : int
    {
        return $this->writeToConnection('UPDATE', $returnAffected, $values);
    }

    /**
     * Perform a DELETE query.
     *
     * @param bool $returnAffected (default:true)
     *
     * @return int
     */
    public function delete(bool $returnAffected = true) : int
    {
        return $this->writeToConnection('DELETE', $returnAffected);
    }

    public function getConnection() : DatabaseConnection
    {
        return $this->connection;
    }

    public function setConnection(DatabaseConnection $connection) : Query
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get the assigned query to calculate the pagination total count.
     *
     * @return ?BaseQuery
     */
    public function getCountQuery() : ?BaseQuery
    {
        return $this->countQuery;
    }

    /**
     * Set the query to calculate pagination total count.
     *
     * @param ?BaseQuery $countQuery
     *
     * @return Query
     */
    public function setCountQuery(?BaseQuery $countQuery) : Query
    {
        $this->countQuery = $countQuery;
        return $this;
    }

    /**
     * Assign a custom callable to create your desired paginator.
     *
     * @param callable $factory
     *
     * @return $this
     */
    public function createPaginatorBy(callable $factory) : Query
    {
        $this->paginatorFactory = $factory;
        return $this;
    }

    /**
     * {@inheritdoc}
     * Reimplemented to ensure string return value.
     *
     * @return string
     **/
    public function __toString() : string
    {
        $rendered = $this->renderIfRenderer();
        if ($rendered instanceof SQLExpression) {
            return SQL::render($rendered, $rendered->getBindings());
        }
        return (string)$rendered;
    }

    /**
     * @return int
     */
    protected function getTotalCount() : int
    {
        $result = $this->readFromConnection($this->getTotalCountQuery())->first();
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * @return BaseQuery
     */
    protected function getTotalCountQuery() : BaseQuery
    {
        if ($this->countQuery) {
            return $this->countQuery;
        }

        $query = clone $this;
        $query->offset(null)->limit(null);
        $query->columns = [];
        $query->select(new Str('COUNT(*) as total'));
        $query->orderBys = [];
        return $query;
    }

    /**
     * @param int $page
     * @param int $perPage
     *
     * @return Result
     */
    protected function runPaginated(int $page, int $perPage) : Result
    {
        $this->offset(($page - 1) * $perPage, $perPage);
        return $this->readFromConnection($this);
    }

    /**
     * @param BaseQuery $query
     *
     * @return Result
     */
    protected function readFromConnection(BaseQuery $query) : Result
    {
        $expression = $this->getRenderer()($query);

        if (!$expression instanceof SQLExpression) {
            return $this->getConnection()->select("$expression");
        }

        return $this->getConnection()->select($expression->__toString(), $expression->getBindings());
    }

    /**
     * @param string $operation (INSERT|UPDATE|REPLACE|DELETE)
     * @param bool $returnResult
     * @param array $values (optional)
     *
     * @return int
     */
    protected function writeToConnection(string $operation, bool $returnResult, array $values = []) : int
    {
        $this->operation = $operation;

        if ($values) {
            $this->values($values);
        }

        $expression = $this->getRenderer()($this);
        $con = $this->getConnection();

        $method = $operation == 'INSERT' ? 'insert' : 'write';

        if ($expression instanceof SQLExpression) {
            return (int)$con->$method($expression->__toString(), $expression->getBindings(), $returnResult);
        }

        return $con->$method("$expression", [], $returnResult);
    }

    /**
     * @return bool
     */
    protected function paginatorClassExists() : bool
    {
        if (!is_bool(static::$paginatorClassExists)) {
            static::$paginatorClassExists = class_exists(Paginator::class);
        }
        return static::$paginatorClassExists;
    }
}