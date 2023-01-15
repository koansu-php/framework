<?php
/**
 *  * Created by mtils on 08.10.2022 at 07:12.
 **/

namespace Koansu\Config;

use ArrayAccess;
use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use Koansu\Config\Processors\ConfigVariablesParser;
use Koansu\Config\Readers\IniFileReader;
use OverflowException;
use Traversable;
use UnderflowException;

use function file_exists;
use function file_get_contents;
use function is_string;
use function json_decode;
use function pathinfo;

use const PATHINFO_EXTENSION;

/**
 * Class Config
 *
 * This is an orchestrating container class. Assign traversable objects to read
 * configurations and callables to post process them.
 *
 */
class Config implements ArrayAccess, IteratorAggregate
{
    /**
     * @var array|null
     */
    protected $compiled;

    /**
     * @var array[]|Traversable[]
     */
    protected $sources = [];

    /**
     * @var callable[]
     */
    protected $postProcessors = [];

    /**
     * @param mixed $offset
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function offsetExists($offset) : bool
    {
        $this->compileIfNeeded();
        return isset($this->compiled[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $this->compileIfNeeded();
        return $this->compiled[$offset] ?? null;
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @noinspection PhpMissingParamTypeInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->compileIfNeeded();
        $this->compiled[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->compiled[$offset]);
        }
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        $this->compileIfNeeded();
        return new ArrayIterator($this->compiled);
    }

    /**
     * Append a source to the sources. Previously set sources will be overwritten
     * by following. So appended will overwrite previously set sources.
     *
     * @param iterable $source
     * @param string|null $name
     */
    public function appendSource(iterable $source, string $name=null)
    {
        $this->sources[$name ?: $this->makeSourceName()] = $source;
        $this->clearCompiled();
    }

    /**
     * Prepend a source to the sources. Previously set sources will be overwritten
     * by following. So prepended will be overwritten by later set sources.
     *
     * @param array|Traversable $source
     * @param string|null $name
     */
    public function prependSource(iterable $source, string $name=null)
    {
        $copy = $this->sources;

        $this->sources = [
            $name ?: $this->makeSourceName() => $source
        ];

        foreach ($copy as $name=>$source) {
            $this->sources[$name] = $source;
        }
        $this->clearCompiled();
    }

    /**
     * Clear the compiled result
     */
    public function clearCompiled()
    {
        $this->compiled = null;
    }

    /**
     * Add a processing callable that works over the built config to do some
     * string replacements or other stuff.
     * The current processed version is passed to it and the second argument is
     * the unprocessed first version.
     *
     * @param callable $processor
     */
    public function appendPostProcessor(callable $processor)
    {
        $this->clearCompiled();
        $this->postProcessors[] = $processor;
    }

    /**
     * Prepend a processing callable.
     * @see self::appendPostProcessor()
     *
     * @param callable $processor
     */
    public function prependPostProcessor(callable $processor)
    {
        $this->clearCompiled();
        $copy = $this->postProcessors;
        $this->postProcessors = [$processor];
        foreach ($copy as $processor) {
            $this->postProcessors[] = $processor;
        }
    }

    /**
     * Merge all sources and post process the result.
     *
     * @param array      $sources
     * @param callable[] $processors
     *
     * @return array
     */
    protected function compile(array $sources, array $processors) : array
    {
        $config = [];
        foreach ($sources as $name=>$source) {
            foreach ($source as $key=>$value) {
                $config[$key] = $value;
            }
        }

        if (!$processors) {
            return $config;
        }

        $processed = $config;
        foreach ($processors as $processor) {
            $processed = $processor($processed, $config);
        }

        return $processed;

    }

    /**
     * Compile the config if no compiled config was compiled already.
     */
    protected function compileIfNeeded()
    {
        if ($this->compiled !== null) {
            return;
        }
        if (!$this->sources) {
            throw new UnderflowException('No sources were added to the config');
        }
        $this->compiled = $this->compile($this->sources, $this->postProcessors);
    }

    /**
     * @return string
     */
    protected function makeSourceName(): string
    {
        for ($i=0; $i<100; $i++) {
            $name = "source-$i";
            if (!isset($this->sources[$name])) {
                return $name;
            }
        }
        throw new OverflowException("Giving up after $i iterations to find an unused source name");
    }

}