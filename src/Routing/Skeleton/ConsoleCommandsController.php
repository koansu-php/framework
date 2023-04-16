<?php
/**
 *  * Created by mtils on 18.12.2022 at 10:18.
 **/

namespace Koansu\Routing\Skeleton;

use Koansu\Console\AnsiRenderer;
use Koansu\Routing\Command;
use Koansu\Routing\ConsoleParameter;
use Koansu\Routing\Exceptions\RouteNotFoundException;
use Koansu\Routing\Contracts\Input;
use Koansu\Routing\Route;
use Koansu\Routing\Contracts\RouteRegistry;
use Koansu\Routing\ConsoleInput;
use Koansu\Skeleton\ConsoleOutput;

use function fnmatch;
use function implode;
use function in_array;
use function max;
use function str_pad;
use function str_repeat;
use function strlen;
use function trim;

class ConsoleCommandsController
{
    /**
     * Show all console commands.
     *
     * @param Input $input
     * @param RouteRegistry $registry
     * @param ConsoleOutput $out
     */
    public function index(
        Input $input,
        RouteRegistry $registry,
        ConsoleOutput $out
    ) : void {
        $commands = $this->getConsoleCommands($registry);
        $maxLength = $this->longestCommandLength($commands);
        $padLength = $maxLength + 8;
        $pattern = trim($input->get('pattern', ''));

        foreach ($commands as $command) {
            if ($pattern && !$this->matches($command->pattern, $pattern)) {
                continue;
            }
            $out->line(
                '<info>' . str_pad(
                    $command->pattern,
                    $padLength
                ) . '</info>' . $command->description
            );
        }
    }

    /**
     * Show help for a single command.
     *
     * @param ConsoleInput $input
     * @param RouteRegistry $registry
     * @param ConsoleOutput $out
     * @param AnsiRenderer $renderer
     */
    public function show(
        ConsoleInput $input,
        RouteRegistry $registry,
        ConsoleOutput $out,
        AnsiRenderer $renderer
    ) : void {
        $command = $input->argument('command_name');
        $commands = $this->getConsoleCommands($registry);

        $indentSize = 2;
        $indent = str_repeat(' ', $indentSize);

        if (!isset($commands[$command])) {
            throw new RouteNotFoundException("Command $command not found.");
        }

        $command = $commands[$command];
        $inlineHelp = [];

        if ($command->options) {
            $inlineHelp[] = $renderer->format('<comment>[options]</comment>');
        }

        if ($argLine = $this->buildArgumentLine($command, $renderer)) {
            $inlineHelp[] = implode(' ', $argLine);
        }

        $inlineHelpString = $inlineHelp ? ' ' . implode(
                ' ',
                $inlineHelp
            ) . ' ' : ' ';

        $out->line('<comment>Usage:</comment>');

        $out->line(
            "$indent<info>$command->pattern</info>$inlineHelpString<mute>$command->description</mute>"
        );

        if ($argLine) {
            $this->printArgumentHelp($command, $out, $indent);
        }

        if ($command->options) {
            $this->printOptionHelp($command, $out, $indent);
        }
    }

    /**
     * @param RouteRegistry $registry
     *
     * @return Command[]
     */
    protected function getConsoleCommands(RouteRegistry $registry): array
    {
        $consoleCommands = [];

        /** @var Route $route */
        foreach ($registry as $route) {
            if (in_array(
                    Input::CLIENT_CONSOLE,
                    $route->clientTypes
                ) && $route->command) {
                $consoleCommands[$route->command->pattern] = $route->command;
            }
        }

        return $consoleCommands;
    }

    /**
     * @param Command[] $commands
     *
     * @return int
     */
    protected function longestCommandLength(iterable $commands): int
    {
        $max = 0;
        foreach ($commands as $command) {
            $max = max($max, strlen($command->pattern));
        }
        return (int)$max;
    }

    /**
     * @param ConsoleParameter[] $parameters
     *
     * @return int
     */
    protected function longestParameterLength(iterable $parameters): int
    {
        $max = 0;
        foreach ($parameters as $parameter) {
            $max = max($max, strlen($parameter->name));
        }
        return (int)$max;
    }

    /**
     * @param Command $command
     * @param AnsiRenderer $renderer
     *
     * @return array
     */
    protected function buildArgumentLine(
        Command $command,
        AnsiRenderer $renderer
    ): array {
        $argLine = [];

        foreach ($command->arguments as $argument) {
            // Hide the first argument
            if ($argument->name == 'command') {
                continue;
            }
            $argLine[] = $argument->required ? "<$argument->name>" : $renderer->format(
                "<mute>[<$argument->name>]</mute>"
            );
        }

        return $argLine;
    }

    /**
     * @param Command $command
     * @param ConsoleOutput $out
     * @param string $indent
     */
    protected function printArgumentHelp(
        Command $command,
        ConsoleOutput $out,
        string $indent
    ) : void {
        $out->line('');
        $out->line('<comment>Arguments:</comment>');

        $longestArg = $this->longestParameterLength($command->arguments);
        $padLength = $longestArg + 6;
        foreach ($command->arguments as $argument) {
            // Hide the first argument
            if ($argument->name == 'command') {
                continue;
            }
            $out->line(
                $indent . str_pad(
                    $argument->name,
                    $padLength
                ) . " <mute>$argument->description</mute>"
            );
        }
    }

    /**
     * @param Command $command
     * @param ConsoleOutput $out
     * @param string $indent
     */
    protected function printOptionHelp(
        Command $command,
        ConsoleOutput $out,
        string $indent
    ) : void {
        $out->line('');
        $out->line('<comment>Options:</comment>');

        $longestArg = $this->longestParameterLength($command->arguments);
        $padLength = $longestArg + 6;
        foreach ($command->options as $option) {
            $out->line(
                $indent . str_pad(
                    $option->name,
                    $padLength
                ) . " <mute>$option->description</mute>"
            );
        }
    }

    /**
     * @param string $commandName
     * @param string $pattern
     *
     * @return bool
     */
    protected function matches(string $commandName, string $pattern): bool
    {
        /** @noinspection PhpStrFunctionsInspection */
        if (strpos($commandName, $pattern) !== false) {
            return true;
        }
        return fnmatch($pattern, $commandName);
    }
}