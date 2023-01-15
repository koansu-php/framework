<?php
/**
 *  * Created by mtils on 26.12.2022 at 06:00.
 **/

namespace Koansu\Pagination;

use Koansu\Core\Url;
use Koansu\Core\Exceptions\ConfigurationException;
use const STR_PAD_LEFT;
use function str_pad;

/**
 * Class Page
 *
 * A Page is one entry in a paginator. Basically it contains
 * a number and an url. All the other methods are just helpers for
 * a cleaner wording when rendering it.
 */
class Page
{
    /**
     * @var array
     */
    protected $values = [];

    /**
     * Page constructor.
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * Return the page index inside pages (starting by 1).
     * Return 0 if the paginator is not LengthAware.
     *
     * @return int
     */
    public function number() : int
    {
        return $this->values['number'];
    }

    /**
     * Return the url to this page.
     *
     * @return Url
     *
     * @throws ConfigurationException
     */
    public function url() : Url
    {
        if (isset($this->values['url']) && $this->values['url'] instanceof  Url) {
            return $this->values['url'];
        }
        throw new ConfigurationException('No url was given by the paginator. Did you perform Paginator::setBaseUrl()?');
    }

    /**
     * Return true if this page is the current page.
     *
     * @return bool
     */
    public function isCurrent() : bool
    {
        return $this->values['is_current'];
    }

    /**
     * Return true if this page is the previous page of the current page.
     *
     * @return bool
     */
    public function isPrevious() : bool
    {
        return $this->values['is_previous'];
    }

    /**
     * Return true if this page is the next page of the current page.
     *
     * @return bool
     */
    public function isNext() : bool
    {
        return $this->values['is_next'];
    }

    /**
     * Return true if this is the first page.
     *
     * @return bool
     */
    public function isFirst() : bool
    {
        return $this->values['is_first'];
    }

    /**
     * Return true if this is the last page.
     *
     * @return bool
     */
    public function isLast() : bool
    {
        return $this->values['is_last'];
    }

    public function __toString() : string
    {
        return $this->isPlaceholder() ? '...' : (string)$this->number();
    }

    /**
     * Return the offset of a database query for that page.
     *
     * @return int
     */
    public function getOffset() : int
    {
        return $this->values['offset'];
    }

    /**
     * Return true if this is just a placeholder and no real page. (... or so)
     *
     * @return bool
     */
    public function isPlaceholder() : bool
    {
        return $this->number() < 1;
    }

    /**
     * Just a helper method for debugging.
     *
     * @return string
     */
    public function dump() : string
    {
        $isFirst = $this->dumpFlag('first', $this->isFirst());
        $isCurrent = $this->dumpFlag('current', $this->isCurrent());
        $isPrevious = $this->dumpFlag('prev', $this->isPrevious());
        $isNext = $this->dumpFlag('next', $this->isNext());
        $isLast = $this->dumpFlag('last', $this->isLast());

        $number = str_pad((string)$this->number(), 10, ' ', STR_PAD_LEFT);

        $flags = "$isCurrent|$isFirst|$isLast|$isPrevious|$isNext";

        $string = str_pad("'$this'", 10, ' ', STR_PAD_LEFT);

        $offset = str_pad((string)$this->getOffset(), 20, ' ', STR_PAD_LEFT);

        return "Page #$number $string offset:$offset flags:$flags";
    }

    protected function dumpFlag(string $name, bool $value) : string
    {
        return $value ? "$name+" : "$name-";
    }
}