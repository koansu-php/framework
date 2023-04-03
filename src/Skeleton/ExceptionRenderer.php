<?php
/**
 *  * Created by mtils on 17.12.2022 at 09:45.
 **/

namespace Koansu\Skeleton;

use Koansu\Console\AnsiRenderer;
use Koansu\Core\Type;
use Koansu\Routing\Contracts\Input;
use Koansu\Core\Response;
use Koansu\Http\HttpResponse;
use Koansu\Routing\ArgvInput;
use Throwable;

use function array_keys;
use function array_shift;
use function array_values;
use function basename;
use function explode;
use function get_class;
use function implode;
use function str_replace;

use const PHP_EOL;

class ExceptionRenderer
{
    public function __invoke(Throwable $e, Input $input) : Response
    {
        if ($input instanceof ArgvInput) {
            return $this->createConsoleExceptionResponse($e, $input);
        }
        return $this->createHttpResponse($e, $input);
    }

    /**
     * @param Throwable $e
     * @param Input $input
     * @return Response
     */
    protected function createConsoleExceptionResponse(Throwable $e, Input $input) : Response
    {
        $string = 'Unsupported input class for console: ' . get_class($input);
        if ($input instanceof ArgvInput) {
            $string = $this->renderConsoleException($e, $input);
        }
        return new Response($string, [], 1, AnsiRenderer::LINE_CONTENT_TYPE);
    }

    /**
     * @param Throwable $e
     * @param ArgvInput $input
     * @return string
     */
    protected function renderConsoleException(Throwable $e, ArgvInput $input) : string
    {
        $lines = [];
        $this->renderConsoleExceptionLines($e, $input->wantsVerboseOutput(), $lines);
        return implode(PHP_EOL, $lines);
    }

    /**
     * @param Throwable $e
     * @param bool $verbose
     * @param array $lines
     */
    protected function renderConsoleExceptionLines(Throwable $e, bool $verbose, array &$lines) : void
    {
        $lines[] = '<error>' . $e->getMessage() . '</error>';
        if(!$verbose) {
            return;
        }
        $trace = explode(PHP_EOL, $e->getTraceAsString());
        foreach ($trace as $line) {
            $lines[] = $this->renderStacktraceLine($line);
        }

        $maxParents = 10;
        $current = $e;
        $loop = 1;

        while ($previous = $current->getPrevious()) {
            $lines[] = '<info>Previous Exception:</info>';
            $this->renderConsoleExceptionLines($previous, true, $lines);

            $current = $previous;
            $loop++;
            if ($loop >= $maxParents) {
                break;
            }
        }

    }

    protected function renderStacktraceLine(string $line) : string
    {
        $numberAndRest = explode(' ', $line, 2);
        $fileAndRest = explode('):', $numberAndRest[1], 2);
        if (!isset($fileAndRest[1])) {
            return '<info>' . $numberAndRest[0] . '</info> ' . $numberAndRest[1];
        }
        return '<info>' . $numberAndRest[0] . '</info> <comment>' . $fileAndRest[0] . '):</comment> ' . $fileAndRest[1];
    }

    /**
     * @param Throwable $e
     * @param Input $input
     * @return HttpResponse
     * @noinspection PhpUnusedParameterInspection
     */
    protected function createHttpResponse(Throwable $e, Input $input) : HttpResponse
    {

        $html = '<html lang="en"><head><title>Application error occurred</title></head>';
        $classShort = Type::short($e);
        $fileShort = basename($e->getFile());

        $html .= '<body>';

        $html .= '<h3><span title="' . get_class($e) . '">Uncaught ' . $classShort . '</span> in <span title="' . $e->getFile() . '">' . $fileShort . '(' . $e->getLine() . ')</span>:</h3>';

        $html .= '<p class="error-message">' . $e->getMessage() . '</p>';

        $html .= '<h4>Stacktrace:</h4>';

        $lines = [];

        $this->renderConsoleExceptionLines($e, true, $lines);

        array_shift($lines);

        $trace = implode('<br>', $lines);

        $replace = [
            '<error>' => '<p class="error-message">',
            '</error>' => '</p>',
            '<info>'   => '<span class="info" style="color: green">',
            '</info>'  => '</span>',
            '<comment>' => '<span class="info" style="color: saddlebrown">',
            '</comment>' => '</span>'
        ];

        $html .= str_replace(array_keys($replace), array_values($replace), $trace);

        $html .= '</body></html>';

        return new HttpResponse($html, [], 500, 'text/html');

    }
}