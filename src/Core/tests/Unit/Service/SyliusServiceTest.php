<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Tests\Unit\Service;

use Castor\Attribute\AsTask;
use Castor\Docker\Service\MySQLService;
use Castor\Docker\Service\PHPService;
use Castor\Docker\Service\PostgresService;
use PHPUnit\Framework\TestCase;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;

final class SyliusServiceTest extends TestCase
{
    public function testAddsPhpGdAndExifExtensions(): void
    {
        $service = new SyliusService();

        $extensions = array_keys((new \ReflectionClass(PHPService::class))
            ->getProperty('extensions')
            ->getValue($service))
        ;

        static::assertContains('gd', $extensions);
        static::assertContains('exif', $extensions);
    }

    public function testExposesLinkedLocalDatabaseEngine(): void
    {
        $service = (new SyliusService())->withDatabaseService(new PostgresService());

        static::assertSame('PostgreSQL', $service->databaseEngine());
    }

    public function testExposesMySqlForLinkedMySqlService(): void
    {
        $service = (new SyliusService())->withDatabaseService(new MySQLService());

        static::assertSame('MySQL', $service->databaseEngine());
    }

    public function testExposesTasksOfRegisteredProviders(): void
    {
        TaskProviderRegistry::register('test_provider', static fn(SyliusService $service): iterable => [[
            'task' => new AsTask('hello', $service->getName() . ':test', 'Test task'),
            'function' => static function (): void {},
        ]]);

        try {
            static::assertContains('app:test:hello', $this->taskNames(new SyliusService()));
        } finally {
            TaskProviderRegistry::unregister('test_provider');
        }
    }

    public function testAlwaysExposesTheFixturesTask(): void
    {
        static::assertContains('app:db:fixtures', $this->taskNames(new SyliusService()));
    }

    /**
     * @return list<string>
     */
    private function taskNames(SyliusService $service): array
    {
        $names = [];

        foreach ($service->getTasks() as $task) {
            /** @var AsTask $asTask */
            $asTask = $task['task'];
            $names[] = $asTask->namespace . ':' . $asTask->name;
        }

        return $names;
    }
}
