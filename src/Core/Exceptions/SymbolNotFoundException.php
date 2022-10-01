<?php
/**
 *  * Created by mtils on 28.08.2022 at 09:19.
 **/

namespace Koansu\Core\Exceptions;

use LogicException;
use Throwable;

use function explode;
use function str_replace;
use function strpos;

/**
 * A SymbolNotFoundException is thrown if a class or method or function was not
 * found. A symbol has to be predefined like a class or a function.
 * Not found Closures and dynamically added properties should not throw a
 * SymbolNotFoundException.
 */
class SymbolNotFoundException extends LogicException
{
    public const CLASS_NOT_FOUND = 404100;
    public const FUNCTION_NOT_FOUND = 404101;
    public const METHOD_NOT_FOUND = 404102;
    public const CONSTANT_NOT_FOUND = 404103;
    public const PROPERTY_NOT_FOUND = 404104;

    /**
     * @var string
     */
    private $symbol = '';

    /**
     * Create the exception. If missing a method or property pass an array
     * [$class, $property] or separate it by '::'.
     *
     * @param string|string[] $symbol
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($symbol = "", int $code = self::CLASS_NOT_FOUND, Throwable $previous = null)
    {
        $symbol = $this->symbolToString($symbol);
        parent::__construct($this->generateMessage($symbol, $code), $code, $previous);
    }

    /**
     * @return string
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * Get the class of the symbol.
     *
     * @return string
     */
    public function getClass() : string
    {
        if ($this->getCode() == self::CLASS_NOT_FOUND) {
            return $this->symbol;
        }
        return $this->getFromSymbol(0);
    }

    /**
     * Get the missing method name.
     *
     * @return string
     */
    public function getMethod() : string
    {
        if ($this->getCode() != self::METHOD_NOT_FOUND) {
            return '';
        }
        return $this->getFromSymbol(1);
    }

    /**
     * Return the function or method.
     *
     * @return string
     */
    public function getFunction() : string
    {
        $code = $this->getCode();
        if ($code === self::METHOD_NOT_FOUND) {
            return $this->getMethod();
        }
        if ($code === self::FUNCTION_NOT_FOUND) {
            return $this->symbol;
        }
        return '';
    }

    /**
     * Return the missing property.
     *
     * @return string
     */
    public function getProperty() : string
    {
        if ($this->getCode() !== self::PROPERTY_NOT_FOUND) {
            return '';
        }
        return $this->getFromSymbol(1);
    }

    /**
     * @param string $symbol
     * @param int $code
     * @return string
     */
    private function generateMessage(string $symbol, int $code) : string
    {
        switch ($code) {
            case self::CLASS_NOT_FOUND:
                return "Class $symbol not found";
            case self::FUNCTION_NOT_FOUND:
                return "Function $symbol not found";
            case self::CONSTANT_NOT_FOUND:
                return "Constant $symbol not found";
            case self::METHOD_NOT_FOUND:
                return "Method $symbol not found";
            case self::PROPERTY_NOT_FOUND:
                return "Property $symbol not found";
            default:
                return "Symbol $symbol not found";
        }
    }

    /**
     * Ensure standard separator between class and method.
     *
     * @param $symbol
     * @return string
     */
    private function symbolToString($symbol) : string
    {
        if (is_array($symbol)) {
            return implode('::', $symbol);
        }
        if (strpos($symbol, '->')) {
            return str_replace('->', '::', $symbol);
        }
        if (strpos($symbol, '@')) {
            return str_replace('@', '::', $symbol);
        }
        return $symbol;
    }

    protected function getFromSymbol(int $index) : string
    {
        $parts = explode('::', $this->symbol);
        // If there is no second segment, this is not a composite symbol
        if (!isset($parts[1])) {
            return '';
        }
        return $parts[$index];
    }
}