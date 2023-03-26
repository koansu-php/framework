<?php
/**
 *  * Created by mtils on 26.03.2023 at 08:20.
 **/

use Koansu\Testing\Benchmark;
use Koansu\Core\Url;

include_once __DIR__."/../../../vendor/autoload.php";

$iterations = 10000;

Benchmark::begin('url-replicate');
$url = new Url('https://github.com');

for ($i=0; $i<$iterations; $i++) {
    $url->path('koansu-php/framework')
        ->query('q','is:issue is:open')
        ->query('label', 'bug');
}

Benchmark::end('url-replicate');

$ms = Benchmark::milliSeconds(Benchmark::totalDuration());

echo "$ms ms\n";