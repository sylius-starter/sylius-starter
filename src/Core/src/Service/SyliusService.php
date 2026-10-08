<?php

declare(strict_types=1);

namespace Castor\Sylius\Service;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Docker\Service\DatabaseServiceInterface;
use Castor\Docker\Service\MariaDBService;
use Castor\Docker\Service\MySQLService;
use Castor\Docker\Service\PostgresService;
use Castor\Docker\Service\SymfonyService;
use Castor\Sylius\App;
use Castor\Sylius\Tasks\B2bTasks;
use Castor\Sylius\Tasks\ImportTasks;
use Castor\Sylius\Tasks\MenuTasks;
use Castor\Sylius\Tasks\PaymentGatewayTasks;
use Castor\Sylius\Tasks\PluginTasks;
use Castor\Sylius\Tasks\ThemeTasks;
use Castor\Sylius\Tasks\UpsunTasks;
use Castor\Sylius\Util\Fixtures;

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

        yield from (new PluginTasks($this->name, $this->getDirectory()))();
        yield from (new PaymentGatewayTasks($this->name, $this->getDirectory()))();
        yield from (new ThemeTasks($this->name, $this->getDirectory()))();
        yield from (new MenuTasks($this->name, $this->getDirectory()))();
        yield from (new ImportTasks($this->name, $this->getDirectory(), $this->getDomains()[0] ?? null))();
        yield from (new B2bTasks($this->name, $this->getDirectory()))();
        yield from (new UpsunTasks($this->name, $this->getDirectory(), $this->databaseEngine()))();

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
