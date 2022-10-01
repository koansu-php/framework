<?php

namespace Koansu\Core\DataStructures;

use Traversable;

class StringList extends Sequence
{

    /**
     * The delimiter.
     *
     * @var string
     **/
    protected $glue = ' ';

    /**
     * The string prefix for __toString.
     *
     * @var string
     **/
    protected $prefix = '';

    /**
     * The string suffix for __toString.
     *
     * @var string
     **/
    protected $suffix = '';

    /**
     * @param array|Traversable|int|string|null $source (optional)
     * @param string                            $glue (default: ' ')
     * @param string                            $prefix (optional)
     * @param string                            $suffix (optional)
     *
     * @see self::setSource
     **/
    public function __construct($source = null, string $glue = ' ', string $prefix = '', string $suffix = '')
    {
        $this->glue = $glue;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        parent::__construct($source);
    }

    /**
     * Return the glue (string between items).
     *
     * @return string
     **/
    public function getGlue() : string
    {
        return $this->glue;
    }

    /**
     * Set the glue (the string between the parts).
     *
     * @param string $glue
     *
     * @return self
     **/
    public function setGlue(string $glue)
    {
        $this->glue = $glue;

        return $this;
    }

    /**
     * Return the string prefix.
     *
     * @return string
     **/
    public function getPrefix() : string
    {
        return $this->prefix;
    }

    /**
     * Set the prefix.
     *
     * @param string $prefix
     *
     * @return self
     **/
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Return the suffix.
     *
     * @return string
     **/
    public function getSuffix() : string
    {
        return $this->suffix;
    }

    /**
     * Set the suffix.
     *
     * @param string $suffix
     *
     * @return self
     **/
    public function setSuffix(string $suffix)
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param array|Traversable|int|string $source (optional)
     *
     * @return self
     **/
    public function setSource($source)
    {
        if (!is_string($source)) {
            return parent::setSource($source);
        }

        if ($this->glue === '') {
            return parent::setSource(str_split($source));
        }

        if (!mb_strlen($source)) {
            $this->source = [];

            return $this;
        }

        $this->source = explode($this->glue, trim($source, $this->glue));

        return $this;
    }

    /**
     * Test if this StringList is equal to a string, another  StringList.
     *
     * @param string|StringList $other
     *
     * @param bool $considerAffix (default=false)
     *
     * @return bool
     */
    public function equals($other, bool $considerAffix=false) : bool
    {

        if ($other instanceof self) {
            return $considerAffix ? ($other->__toString() == $this->__toString())
                                  : ($other->getSource() == $this->source);
        }

        $other = "$other";

        if ($considerAffix) {
            return $other == $this->__toString();
        }

        $cleaned = $other;

        if ($this->prefix && mb_strpos($other, $this->prefix) === 0) {
            $cleaned = mb_substr($other, mb_strlen($this->prefix));
        }

        if ($this->suffix && mb_strpos($other, $this->suffix)) {
            $cleaned = mb_substr($cleaned, 0, 0-mb_strlen($this->suffix));
        }

        if ($this->hasDifferentAffixes()){
            $cleaned = trim($cleaned, $this->glue);
        }

        $me = implode($this->glue, $this->source);

        return ($me == $other || $me == $cleaned);

    }

    /**
     * Copies the list or its extended class.
     *
     * @return self
     */
    public function copy()
    {
        return parent::copy()->setGlue($this->glue)
                             ->setPrefix($this->prefix)
                             ->setSuffix($this->suffix);
    }

    /**
     * @return string
     **/
    public function __toString()
    {
        if (!$this->prefix && !$this->suffix) {
            return implode($this->glue, $this->source);
        }

        $middle = implode($this->glue, $this->source);

        // If not empty
        if ($middle) {
            return $this->prefix.$middle.$this->suffix;
        }

        // If suffix, prefix and glue are equal, normalize the result
        if ($this->glue === $this->prefix && $this->glue === $this->suffix) {
            return $this->prefix;
        }
        return $this->prefix.$this->suffix;
    }

    /**
     * Returns true if no prefix or suffix set or if one of them differs
     * from glue.
     *
     * @return bool
     */
    protected function hasDifferentAffixes() : bool
    {
        if ($this->prefix && $this->prefix != $this->glue) {
            return false;
        }

        if ($this->suffix && $this->suffix != $this->glue) {
            return false;
        }

        return true;
    }
}
