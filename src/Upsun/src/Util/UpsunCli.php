<?php

declare(strict_types=1);

namespace SyliusStarter\Upsun\Util;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\run;

/**
 * Runs Upsun commands on the host, through the Symfony CLI ("symfony cloud:…")
 * or, when it is missing, the Upsun CLI ("upsun …").
 */
final readonly class UpsunCli
{
    /**
     * @var \Closure(list<string>, string, bool): array{int, string}
     */
    private \Closure $executor;

    /**
     * @param ?\Closure(list<string>, string, bool): array{int, string} $executor runs a command in a directory, interactively
     *                                                                            or not, and returns its exit code and output
     */
    public function __construct(
        private string $binary,
        ?\Closure $executor = null,
    ) {
        $this->executor = $executor ?? self::castorExecutor(...);
    }

    public static function detect(?\Closure $executor = null): ?self
    {
        $finder = new ExecutableFinder();

        foreach (['symfony', 'upsun'] as $binary) {
            if (null !== $finder->find($binary)) {
                return new self($binary, $executor);
            }
        }

        return null;
    }

    public function binary(): string
    {
        return $this->binary;
    }

    /**
     * The command as the user types it, "symfony cloud:login" or "upsun login".
     */
    public function commandLine(string $command): string
    {
        return implode(' ', $this->command($command));
    }

    /**
     * @param list<string> $arguments
     */
    public function succeeds(string $command, string $directory, array $arguments = []): bool
    {
        return 0 === ($this->executor)([...$this->command($command), ...$arguments], $directory, false)[0];
    }

    /**
     * @param list<string> $arguments
     */
    public function passthru(string $command, string $directory, array $arguments = []): int
    {
        return ($this->executor)([...$this->command($command), ...$arguments], $directory, true)[0];
    }

    /**
     * @param list<string> $command
     *
     * @return array{int, string}
     */
    public function git(array $command, string $directory): array
    {
        return ($this->executor)(['git', ...$command], $directory, false);
    }

    /**
     * @return list<string>
     */
    private function command(string $command): array
    {
        if ('symfony' !== $this->binary) {
            return [$this->binary, $command];
        }

        // The Symfony CLI names the Upsun push "cloud:deploy".
        return [$this->binary, 'cloud:' . ('push' === $command ? 'deploy' : $command)];
    }

    /**
     * @param list<string> $command
     *
     * @return array{int, string}
     */
    private static function castorExecutor(array $command, string $directory, bool $interactive): array
    {
        $context = context()->withWorkingDirectory($directory)->withAllowFailure();
        $context = $interactive
            ? $context->withTty(Process::isTtySupported())
            : $context->withQuiet();

        $process = run($command, context: $context);

        return [$process->getExitCode() ?? 1, $process->getOutput()];
    }
}
