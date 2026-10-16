<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Service;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Docker\Service\DatabaseServiceInterface;
use Castor\Docker\Service\MariaDBService;
use Castor\Docker\Service\MySQLService;
use Castor\Docker\Service\PhpMode;
use Castor\Docker\Service\PostgresService;
use Castor\Docker\Service\SymfonyService;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\Core\Util\Assets;
use SyliusStarter\Core\Util\Fixtures;

use function Castor\context;
use function Castor\Docker\docker_compose;

class SyliusService extends SymfonyService
{
    private iterable $tasks = [];

    private ?DatabaseServiceInterface $databaseService = null;

    public function __construct(string $name = 'app')
    {
        parent::__construct($name);

        $this->addExtension('gd');
        $this->addExtension('imagick');
        $this->addExtension('exif');
    }

    public function getTasks(): iterable
    {
        foreach (parent::getTasks() as $task) {
            if ('cache-clear' === $task['task']->name && $this->name === $task['task']->namespace) {
                $clear = $task['function'];
                $task['function'] = function (bool $warm = true) use ($clear): void {
                    $clear($warm);
                    $this->restartFrankenPhp();
                };
            }

            yield $task;
        }

        // Tasks contributed by the installed sylius-starter/* packages
        yield from TaskProviderRegistry::tasksFor($this);

        yield from $this->tasks;

        yield [
            'task' => new AsTask('fixtures', $this->name . ':db', 'Loads fixtures', ['sylius:fixtures']),
            /**
             * @param list<string> $rawTokens
             */
            'function' => function (#[AsRawTokens] array $rawTokens = []): void {
                $app = new App($this->getName(), $this->getDirectory());
                Fixtures::load($app, ...$rawTokens);
            },
        ];

        yield [
            'task' => new AsTask('install', $this->name . ':assets', 'Installs Yarn dependencies and Symfony bundle assets'),
            'function' => function (): void {
                Assets::install(new App($this->getName(), $this->getDirectory()));
            },
        ];

        yield [
            'task' => new AsTask('build', $this->name . ':assets', 'Builds shop/admin Webpack Encore assets'),
            'function' => function (): void {
                Assets::build(new App($this->getName(), $this->getDirectory()));
            },
        ];
    }

    /**
     * A cache:clear writes the new cache under new file names (the container class carries a hash), and the
     * long-lived FrankenPHP server keeps the scripts of the old one in its opcache: about 50 MB per clear. Once
     * it is full, opcache schedules a restart that runs while other threads still read the old memory, and the
     * server dies with "zend_mm_heap corrupted" (after 3 or 4 theme switches with the default 256 MB). Restarting
     * the server instead starts from an empty opcache, safely, in under a second.
     */
    private function restartFrankenPhp(): void
    {
        if (PhpMode::FrankenPhp !== $this->mode) {
            return;
        }

        $quiet = context()->withQuiet();

        if ('' === trim(docker_compose(['ps', '--status', 'running', '--quiet', $this->name], $quiet)->getOutput())) {
            return;
        }

        docker_compose(['restart', $this->name], $quiet);

        // Wait for Caddy to answer, like the healthcheck does, so the next request does not hit a 502.
        for ($attempt = 0; $attempt < 60; ++$attempt) {
            $ready = docker_compose(['exec', '-T', $this->name, 'curl', '-fso', '/dev/null', 'http://localhost:2019/metrics'], $quiet->withAllowFailure());

            if ($ready->isSuccessful()) {
                return;
            }

            usleep(500_000);
        }
    }

    public function withDatabaseService(DatabaseServiceInterface $databaseService): static
    {
        parent::withDatabaseService($databaseService);
        $this->databaseService = $databaseService;

        return $this;
    }

    public function databaseEngine(): ?string
    {
        return match (true) {
            $this->databaseService instanceof MySQLService,
            $this->databaseService instanceof MariaDBService => 'MySQL',
            $this->databaseService instanceof PostgresService => 'PostgreSQL',
            default => null,
        };
    }

    public function withTasks(iterable $tasks): self
    {
        $currentTasks = $this->tasks;

        $this->tasks = (static function () use ($currentTasks, $tasks): \Generator {
            yield from $currentTasks;
            yield from $tasks;
        })();

        return $this;
    }
}
