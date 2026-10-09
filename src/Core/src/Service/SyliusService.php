<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Service;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Docker\Service\DatabaseServiceInterface;
use Castor\Docker\Service\MariaDBService;
use Castor\Docker\Service\MySQLService;
use Castor\Docker\Service\PostgresService;
use Castor\Docker\Service\SymfonyService;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\Core\Util\Assets;
use SyliusStarter\Core\Util\Fixtures;

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
        yield from parent::getTasks();

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
