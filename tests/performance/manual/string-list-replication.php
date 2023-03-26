<?php
/**
 *  * Created by mtils on 26.03.2023 at 08:20.
 **/

use Koansu\Core\DataStructures\StringList;
use Koansu\Testing\Benchmark;

include_once __DIR__."/../../../vendor/autoload.php";

$iterations = 50000;

Benchmark::begin('string-list-replicate');

for ($i=0; $i<$iterations; $i++) {
    $stringList = new StringList(['a','b','c'], '/', '/root/',':foo');
    $new = $stringList->copy()->copy()->copy();
}

Benchmark::end('string-list-replicate');

$ms = Benchmark::milliSeconds(Benchmark::totalDuration());

echo "$ms ms\n";