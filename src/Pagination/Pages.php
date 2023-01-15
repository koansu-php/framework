<?php
/**
 *  * Created by mtils on 26.12.2022 at 06:05.
 **/

namespace Koansu\Pagination;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Koansu\Core\Contracts\Result;
use Koansu\Core\Exceptions\NotWritableException;

/**
 * Class Pages
 *
 * The pages are an object that is used only once. If something in the pagination changes
 * you have to create a new Pages object.
 */
class Pages implements Result, ArrayAccess, Countable
{
    /**
     * @var array
     */
    protected $pages = [];

    /**
     * @var int
     */
    protected $totalPageCount = 0;

    /**
     * @var Paginator
     */
    protected $creator;

    /**
     * @var bool
     */
    protected $isSqueezed = false;

    /**
     * @var Page
     */
    protected $firstPage;

    /**
     * @var Page
     */
    protected $lastPage;

    /**
     * @var Page
     */
    protected $currentPage;

    /**
     * @var Page
     */
    protected $previousPage;

    /**
     * @var Page
     */
    protected $nextPage;

    /**
     * Pages constructor.
     *
     * @param Paginator $paginator
     * @param int       $totalPageCount (optional)
     */
    public function __construct(Paginator $paginator, int $totalPageCount=0)
    {
        $this->creator = $paginator;
        $this->totalPageCount = $totalPageCount;
    }

    /**
     * Add a new page.
     *
     * @param Page $page
     *
     * @return $this
     */
    public function add(Page $page) : Pages
    {
        $next = count($this->pages) + 1;

        $this->pages[$next] = $page;

        if ($page->isPlaceholder()) {
            $this->isSqueezed = true;
        }

        if ($page->isFirst()) {
            $this->firstPage = $page;
        }

        if ($page->isLast()) {
            $this->lastPage = $page;
        }

        if ($page->isCurrent()) {
            $this->currentPage = $page;
        }

        if ($page->isPrevious()) {
            $this->previousPage = $page;
        }

        if ($page->isNext()) {
            $this->nextPage = $page;
        }

        return $this;
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return ArrayIterator An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->pages);
    }

    /**
     * Whether an offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be cast to boolean if non-boolean was returned.
     * @since 5.0.0
     * @noinspection PhpMissingParamTypeInspection
     */
    public function offsetExists($offset) : bool
    {
        return isset($this->pages[$offset]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->pages[$offset];
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new NotWritableException('You cannot set Pages directly. Use self::add()');
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new NotWritableException('You unset Pages. Use a new Pages object.');
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count() : int
    {
        return count($this->pages);
    }

    /**
     * @return Page|null
     */
    public function first() : ?Page
    {
        return $this->firstPage;
    }

    /**
     * @return Page|null
     */
    public function last() : ?Page
    {
        return $this->lastPage;
    }

    /**
     * @return Page|null
     */
    public function current() : ?Page
    {
        return $this->currentPage;
    }

    /**
     * @return Page|null
     */
    public function previous() : ?Page
    {
        return $this->previousPage;
    }

    /**
     * @return Page|null
     */
    public function next() : ?Page
    {
        return $this->nextPage;
    }

    /**
     * @return bool
     */
    public function hasOnlyOnePage() : bool
    {
        return $this->count() == 1;
    }

    /**
     * @return bool
     */
    public function hasMoreThanOnePage() : bool
    {
        return $this->count() > 1;
    }

    /**
     * Return the total amount of pages. This only differs when you have a
     * squeezed pagination.
     *
     * @return int
     */
    public function totalPageCount() : int
    {
        return $this->totalPageCount ?: $this->count();
    }

    /**
     * Return true if these pages are squeezed (have placeholders in the middle)
     *
     * @return bool
     */
    public function isSqueezed() : bool
    {
        return $this->isSqueezed;
    }

    /**
     * @return bool
     */
    public function isEmpty() : bool
    {
        return $this->count() == 0;
    }

}