<?php
/**
 *  * Created by mtils on 15.01.2023 at 09:10.
 **/

namespace Koansu\Text;

use Countable;
use Koansu\Core\DataStructures\ObjectSet;
use Koansu\Core\Exceptions\HandlerNotFoundException;
use Koansu\Text\Contracts\StringConverter as StringConverterContract;

class StringConverter implements StringConverterContract, Countable
{
    /**
     * @var ObjectSet|StringConverterContract[]
     */
    protected $converters;

    public function __construct()
    {
        $this->converters = new ObjectSet();
    }

    public function convert(string $text, string $outEncoding, string $inEncoding = ''): string
    {
        /** @var StringConverterContract $converter */
        $converter = $this->converters->firstObjectThat('canConvert', [$outEncoding]);
        return $converter->convert($text, $outEncoding, $inEncoding);
    }

    public function canConvert(string $encoding) : bool
    {
        try {
            $this->converters->firstObjectThat('canConvert', [$encoding]);
            return true;
        } catch (HandlerNotFoundException $e) {
            return false;
        }
    }

    public function encodings() : array
    {
        $encodings = [];
        foreach ($this->converters as $candidate) {
            foreach ($candidate->encodings() as $encoding) {
                $encodings[$encoding] = true;
            }
        }
        return array_keys($encodings);
    }

    public function add(StringConverterContract $converter)
    {
        $this->converters->add($converter);
    }

    public function remove(StringConverterContract $converter)
    {
        $this->converters->remove($converter);
    }

    public function count() : int
    {
        return $this->converters->count();
    }
}