<?php
/**
 *  * Created by mtils on 26.12.2022 at 05:59.
 **/

namespace Koansu\Pagination;

use ArrayIterator;
use Countable;
use Koansu\Core\Contracts\Result;
use Koansu\Core\Type;
use Koansu\Core\Url;
use Koansu\Core\ResultTrait;

use function array_slice;
use function call_user_func;
use function ceil;
use function filter_var;
use function is_bool;
use function is_callable;
use function is_numeric;

use const FILTER_VALIDATE_INT;

/**
 * Class Paginator
 *
 * A Paginator is used to paginate a result.
 * The IteratorAggregate interface is used to return the result items
 * not the pages. The Countable interface returns the count of
 * limited results. So with a totalCount of 1000 and 10 per page it
 * would return 10 (or less on the last page).
 * After some years working with a pagination interface and an implementation
 * it showed that there is no interface for it. Use this class if it is handy,
 * if you like something else do not use it.
 */
class Paginator implements Result, Countable
{
    use ResultTrait;

    /**
     * @var Pages
     */
    protected $pages;

    /**
     * @var int
     */
    protected $currentPageNumber = 1;

    /**
     * @var int
     */
    protected $perPage;

    /**
     * @var int|null
     */
    protected $totalCount;

    /**
     * @var callable
     */
    protected $totalCountProvider;

    /**
     * @var Url
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $pageParameterName;

    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var bool|null
     */
    protected $manuallySetHasMore = null;

    /**
     * @var array
     */
    protected $addToQuery = [];

    /**
     * @var int
     */
    protected static $perPageDefault = 15;

    /**
     * @var string
     */
    protected static $defaultPageParameterName = 'page';

    /**
     * @var int
     */
    protected static $defaultSqueeze = 10;

    /**
     * @var int
     */
    protected static $defaultSqueezeSpace = 2;

    /**
     * Paginator constructor.
     *
     * @param int $currentPage (optional)
     * @param ?int $perPage
     */
    public function __construct(int $currentPage=1, int $perPage=null)
    {
        $this->setPagination($currentPage, $perPage);
        $this->pageParameterName = static::$defaultPageParameterName;
    }

    /**
     * Iterate the ITEMS (not pages)
     *
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Return all pages. By default, it returns a squeezed list of pages for "..."
     * buttons somewhere in the middle. To really get all pages pass 0.
     * Without a total count this is not used.
     *
     * @param int|null $squeezeTo (optional)
     *
     * @return Pages
     */
    public function pages(int $squeezeTo=null) : Pages
    {
        if (!$this->pages) {
            $this->pages = $this->buildPages($squeezeTo === null ? static::$defaultSqueeze : $squeezeTo);
        }
        return $this->pages;
    }

    /**
     * Get the number of the current page.
     *
     * @return int
     */
    public function getCurrentPageNumber() : int
    {
        return $this->currentPageNumber;
    }

    /**
     * Get the number of items per page.
     *
     * @return int
     */
    public function getPerPage() : int
    {
        return $this->perPage;
    }

    /**
     * Set current page and perPage. The per page is optional and has to
     * be set to a default value by the paginator itself.
     *
     * @param int $currentPage
     * @param int|null $perPage (optional)
     *
     * @return Paginator
     */
    public function setPagination(int $currentPage, int $perPage = null) : Paginator
    {

        $this->currentPageNumber = $this->isValidPageNumber($currentPage) ? $currentPage : 1;
        $defaultPerPage = $this->perPage ?: static::$perPageDefault;
        $this->perPage = $perPage ?: $defaultPerPage;
        $this->pages = null;
        return $this;
    }

    /**
     * Set the already limited database result. Pass the $totalCount to make
     * this a "length aware" paginator. Passing a callable will not trigger any
     * further queries until you really need pages. This way you can use a
     * paginator as a chunked result set without the cost of an additional query.
     * Or you pass a boolean value to indicate only there are more results.
     *
     * @param iterable               $items
     * @param int|callable|bool|null $totalOrHasMore (optional)
     *
     * @return $this
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setResult(iterable $items, $totalOrHasMore = null) : Paginator
    {
        $this->items = Type::toArray($items);
        $this->manuallySetHasMore = null;

        if (is_numeric($totalOrHasMore)) {
            $this->totalCount = $totalOrHasMore;
        }
        if (is_callable($totalOrHasMore)) {
            $this->totalCountProvider = $totalOrHasMore;
        }
        if (is_bool($totalOrHasMore)) {
            $this->manuallySetHasMore = $totalOrHasMore;
        }
        $this->pages = null;
        return $this;
    }

    /**
     * Return true if this paginator was constructed with a total count.
     * Without a total count it is not length aware and can just
     * know if it is on the first page.
     * This method also returns true if you pass a callable in setResult.
     *
     * @return int|null
     */
    public function getTotalCount() : ?int
    {
        if ($this->totalCount === null && $this->totalCountProvider) {
            $this->totalCount = call_user_func($this->totalCountProvider, $this);
        }
        return $this->totalCount;
    }

    /**
     * Get the passed totalCount or call the totalCount callable and return its
     * result.
     *
     * @return bool
     */
    public function hasTotalCount() : bool
    {
        if ($this->totalCountProvider) {
            return true;
        }
        return $this->totalCount !== null;
    }

    /**
     * Return the base url. With this url the page urls will be built.
     *
     * @return Url
     */
    public function getBaseUrl() : Url
    {
        return $this->baseUrl;
    }

    /**
     * Set the base url.
     *
     * @param Url $url
     *
     * @return Paginator
     */
    public function setBaseUrl(Url $url) : Paginator
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * Get the (GET) parameter name for applying the page.
     *
     * @return string
     */
    public function getPageParameterName() : string
    {
        return $this->pageParameterName;
    }

    /**
     * Set the (GET) parameter name for applying the page.
     *
     * @param string $name
     *
     * @return Paginator
     */
    public function setPageParameterName(string $name) : Paginator
    {
        $this->pageParameterName = $name;
        return $this;
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
        return count($this->items);
    }

    /**
     * Return the offset for a database query (or array_slice) matching the
     * currentPageNumber and perPage.
     *
     * @return int
     */
    public function getOffset() : int
    {
        return ($this->currentPageNumber-1)*$this->perPage;
    }

    /**
     * Slice a complete result into the desired page/perPage.
     *
     * @param iterable $completeResult
     *
     * @return array
     */
    public function slice(iterable $completeResult) : array
    {
        $all = Type::toArray($completeResult);
        return array_slice($all, $this->getOffset(), $this->perPage);
    }

    /**
     * Add query parameters that will always be added to the url.
     *
     * @param array $query
     * @return Paginator
     */
    public function addToUrl(array $query): Paginator
    {
        $this->addToQuery = $query;
        return $this;
    }


    /**
     * Get the default perPage value.
     *
     * @return int
     */
    public static function getPerPageDefault() : int
    {
        return static::$perPageDefault;
    }

    /**
     * Set the default perPage value.
     *
     * @param int $perPage
     */
    public static function setPerPageDefault(int $perPage)
    {
        static::$perPageDefault = $perPage;
    }

    /**
     * Return the default page parameter name.
     *
     * @return string
     */
    public static function getDefaultPageParameterName() : string
    {
        return static::$defaultPageParameterName;
    }

    /**
     * Set the default page parameter name.
     *
     * @param string $name
     */
    public static function setDefaultPageParameterName(string $name)
    {
        static::$defaultPageParameterName = $name;
    }

    /**
     * Get the default number of maximum rendered pages.
     *
     * @return int
     */
    public static function getDefaultSqueeze() : int
    {
        return static::$defaultSqueeze;
    }

    /**
     * Set the amount of maximum pages. Set it to null to return non squeezed
     * results by default.
     *
     * @param int $squeeze
     */
    public static function setDefaultSqueeze(int $squeeze)
    {
        static::$defaultSqueeze = $squeeze;
    }

    /**
     * Get the default space between the placeholder (...) page and its closest
     * end.
     *
     * @return int
     */
    public static function getDefaultSqueezeSpace() : int
    {
        return static::$defaultSqueezeSpace;
    }

    /**
     * Get the default space between the placeholder (...) page and its closest
     * end.
     *
     * @param int $space
     */
    public static function setDefaultSqueezeSpace(int $space)
    {
        static::$defaultSqueezeSpace = $space;
    }

    /**
     * @param int $squeezeTo
     *
     * @return Pages
     */
    protected function buildPages(int $squeezeTo) : Pages
    {
        if (!$this->hasTotalCount()) {
            return $this->buildLengthUnaware();

        }

        $totalCount = $this->getTotalCount();
        $numberOfPages = $totalCount ? ceil($totalCount/$this->perPage) : 0;

        if ($squeezeTo && $numberOfPages > $squeezeTo) {
            return $this->buildSqueezed($squeezeTo, $numberOfPages);
        }

        return $this->buildLengthAware($numberOfPages);
    }

    /**
     * Build pages if total count is known.
     *
     * @param int $numberOfPages
     *
     * @return Pages
     */
    protected function buildLengthAware(int $numberOfPages) : Pages
    {
        $pages = $this->newPages();

        $totalCount = $this->getTotalCount();

        if ($totalCount < 1) {
            return $pages;
        }

        return $this->addPages($pages, 1, $numberOfPages);

    }

    /**
     * Build pages when no total count is available.
     *
     * @return Pages
     */
    protected function buildLengthUnaware() : Pages
    {
        $pages = $this->newPages();

        $itemCount = count($this->items);

        if ($itemCount < 1) {
            return $pages;
        }

        $perhapsMorePages = is_bool($this->manuallySetHasMore) ? $this->manuallySetHasMore : $itemCount >= $this->perPage;
        $pageParameter = $this->getPageParameterName();
        $currentPage = $this->currentPageNumber;

        $baseUrl = $this->baseUrl ?: '';
        if ($baseUrl && $this->addToQuery) {
            $baseUrl = $baseUrl->query($this->addToQuery);
        }

        for ($page=1; $page <= ($currentPage+1); $page++) {

            if (!$perhapsMorePages && $page > $currentPage) {
                break;
            }

            $pages->add($this->newPage([
                                           'number'      => $page,
                                           'url'         => $baseUrl ? $baseUrl->query($pageParameter, $page) : '',
                                           'is_current'  => $page == $currentPage,
                                           'is_previous' => $page == $currentPage-1,
                                           'is_next'     => $page == $currentPage+1,
                                           'is_first'    => $page == 1,
                                           'is_last'     => $page >= $currentPage && !$perhapsMorePages,
                                           'offset'      => ($page-1) * $this->perPage
                                       ]));
        }

        return $pages;

    }

    /**
     * @param int $squeezeTo
     * @param int $numberOfPages
     *
     * @return Pages
     */
    protected function buildSqueezed(int $squeezeTo, int $numberOfPages) : Pages
    {

        if ($this->currentPageNumber < $squeezeTo - static::$defaultSqueezeSpace) {
            return $this->buildStartEmphasized($squeezeTo, $numberOfPages);
        }

        if ($this->currentPageNumber - (static::$defaultSqueezeSpace+1) > ($numberOfPages - $squeezeTo)) {
            return $this->buildEndEmphasized($squeezeTo, $numberOfPages);
        }

        // centered
        return $this->buildCentered($squeezeTo, $numberOfPages);
    }

    /**
     * Build pages for one of the first pages. Many pages at the start
     * will be visible, less at the end.
     *
     * @param int $squeezeTo
     * @param int $numberOfPages
     *
     * @return Pages
     */
    protected function buildStartEmphasized(int $squeezeTo, int $numberOfPages) : Pages
    {
        $pages = $this->newPages($numberOfPages);

        $this->addPages($pages, 1, $squeezeTo - static::$defaultSqueezeSpace);

        $this->addPlaceHolder($pages);

        $this->addPages($pages, $numberOfPages - (static::$defaultSqueezeSpace - 1), $numberOfPages);

        return $pages;

    }

    /**
     * Build pages for the last pages. Many pages at the end will be visible,
     * less at the start.
     *
     * @param int $squeezeTo
     * @param int $numberOfPages
     *
     * @return Pages
     */
    protected function buildEndEmphasized(int $squeezeTo, int $numberOfPages) : Pages
    {
        $pages = $this->newPages($numberOfPages);

        $this->addPages($pages, 1, static::$defaultSqueezeSpace);

        $this->addPlaceHolder($pages);

        $leftOffset = $squeezeTo - (static::$defaultSqueezeSpace + 1);
        $this->addPages($pages, $numberOfPages - $leftOffset, $numberOfPages);

        return $pages;
    }

    /**
     * Build a paginator squeezed to the center. Many pages in the center will
     * be visible and just 1 at the start and one at the end.
     *
     * @param int $squeezeTo
     * @param int $numberOfPages
     *
     * @return Pages
     */
    protected function buildCentered(int $squeezeTo, int $numberOfPages) : Pages
    {
        $pages = $this->newPages($numberOfPages);

        $midLength = $squeezeTo - 2;
        $midOffset = $this->currentPageNumber - $midLength/2;

        $this->addPages($pages, 1, 1);
        $this->addPlaceHolder($pages);

        $this->addPages($pages, $midOffset, $midOffset+$midLength-1);

        $this->addPlaceHolder($pages);

        $this->addPages($pages, $numberOfPages, $numberOfPages);

        return $pages;
    }

    /**
     * @param Pages $pages
     * @param int   $from
     * @param int   $to
     *
     * @return Pages
     */
    protected function addPages(Pages $pages, int $from, int $to) : Pages
    {

        $items = $from == 1 ? 0 : ($from-1) * $this->perPage;
        $totalCount = $this->getTotalCount();
        $baseUrl = $this->baseUrl ?: '';
        if ($baseUrl && $this->addToQuery) {
            $baseUrl = $baseUrl->query($this->addToQuery);
        }

        for ($page=$from; $page <= $to; $page++) {

            $items += $this->perPage;

            $pageObject = $this->newPage([
                                             'number'      => $page,
                                             'url'         => $baseUrl ? $baseUrl->query($this->pageParameterName, $page) : '',
                                             'is_current'  => $page == $this->currentPageNumber,
                                             'is_previous' => $page == $this->currentPageNumber-1,
                                             'is_next'     => $page == $this->currentPageNumber+1,
                                             'is_first'    => $page == 1,
                                             'is_last'     => $items >= $totalCount,
                                             'offset'      => ($page-1) * $this->perPage
                                         ]);

            $pages->add($pageObject);

        }

        return $pages;
    }

    /**
     * Adds a placeholder (...) page to make it squeezed.
     *
     * @param Pages $pages
     */
    protected function addPlaceHolder(Pages $pages)
    {
        $pages->add($this->newPage([
                                       'number' => 0,
                                       'url' => '',
                                       'is_current' => false,
                                       'is_previous' => false,
                                       'is_next' => false,
                                       'is_first' => false,
                                       'is_last' => false,
                                       'offset' => -1
                                   ]));
    }

    /**
     * @param array $values
     *
     * @return Page
     */
    protected function newPage(array $values) : Page
    {
        return new Page($values);
    }

    /**
     * @param int $totalPageCount
     *
     * @return Pages
     */
    protected function newPages(int $totalPageCount=0) : Pages
    {
        return new Pages($this, $totalPageCount);
    }

    /**
     * @param mixed $page
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    protected function isValidPageNumber($page) : bool
    {
        return is_numeric($page) && $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
    }

}