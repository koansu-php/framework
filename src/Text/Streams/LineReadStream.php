<?php
/**
 *  * Created by mtils on 15.01.2023 at 08:32.
 **/

namespace Koansu\Text\Streams;

use Countable;
use Koansu\Core\Stream;

use function feof;
use function fgets;
use function rtrim;

class LineReadStream extends Stream implements Countable
{
    /**
     * Return the amount of lines.
     *
     * @return int
     **/
    public function count() : int
    {
        if (!$this->resource) {
            return 0;
        }
        $this->rewind();
        $handle = $this->resource();
        $lineCount = 0;

        while (!feof($handle)) {
            $line = $this->readLine($handle, $this->chunkSize);
            if ($line !== '') {
                ++$lineCount;
            }
        }

        return $lineCount;
    }

    protected function readNext($handle, int $chunkSize): ?string
    {
        if (feof($handle)) {
            return null;
        }

        $line = $this->readLine($handle, $chunkSize);

        return $line === '' ? $this->readNext($handle, $chunkSize) : $line;
    }

}