<?php
/**
 *  * Created by mtils on 17.12.2022 at 21:37.
 **/

namespace Koansu\Testing\Skeleton;


use Koansu\Core\Response;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Contracts\MiddlewareCollection;
use Koansu\Routing\HttpInput;
use Koansu\Routing\RoutedInputHandler;
use Koansu\Skeleton\AppExtension;
use Koansu\Skeleton\Application;
use Koansu\Testing\Benchmark;
use Koansu\Testing\BenchmarkRenderer\JSConsoleRenderer;
use Koansu\Core\Type;

use function is_object;
use function method_exists;
use function str_replace;

class BenchmarkExtension extends AppExtension
{
    public function init(): void
    {
        if (defined('APPLICATION_START')) {
            Benchmark::raw(['name' => 'Application Start', 'time' => APPLICATION_START]);
        }

        $this->app->onAfter(Application::STEP_INIT, function () {
            Benchmark::mark('Initialized');
        });
        $this->app->onAfter(Application::STEP_CONFIGURE, function() {
            Benchmark::mark('Configured');
        });
        $this->app->onAfter(Application::STEP_BIND, function() {
            Benchmark::mark('Bound');
        });
        $this->app->onAfter(Application::STEP_BOOT, function() {
            Benchmark::mark('Booted');
        });

        $this->app->onAfter(MiddlewareCollection::class, function (MiddlewareCollection $middlewares) {
            $middlewares->add('benchmark', function (Input $input, callable $next) {
                if (!$input instanceof HttpInput) {
                    return $next($input);
                }
                /** @var Response $response */
                $response = $next($input);
                $renderer = new JSConsoleRenderer();
                $benchResult = $renderer->render(Benchmark::instance());
                $payload = $response->payload;
                if (!Type::isStringable($payload)) {
                    return $response;
                }
                $output = is_object($response->payload) && method_exists($response->payload, '__toString') ? $response->payload->__toString() : (string)$response->payload;
                return $response->withPayload(str_replace('</body>', "$benchResult\n</body>", $output));
            })->after('handle');
        });

        $this->app->onAfter(RoutedInputHandler::class, function (RoutedInputHandler $handler) {
            $handler->onBefore('call', function () {
                Benchmark::mark('Routed');
            });
            $handler->onAfter('call', function () {
                Benchmark::mark('Performed');
            });
        });

    }

}