<?php

declare(strict_types=1);

namespace Unit\Tasks;

use Castor\Attribute\AsTask;
use Castor\Container;
use Castor\Sylius\Tasks\UpsunTasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(UpsunTasks::class)]
final class UpsunTasksTest extends TestCase
{
    private string $directory;

    private Filesystem $filesystem;

    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/upsun_tasks_test_' . uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->directory);
        $this->output = new BufferedOutput();

        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Container::class, 'fs'))->setValue($container, $this->filesystem);
        (new \ReflectionProperty(Container::class, 'symfonyStyle'))->setValue(
            $container,
            new SymfonyStyle(new ArrayInput([]), $this->output),
        );
        Container::set($container);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testRegistersCheckTask(): void
    {
        $tasks = iterator_to_array((new UpsunTasks('app', $this->directory))());

        static::assertCount(1, $tasks);
        static::assertInstanceOf(AsTask::class, $tasks[0]['task']);
        static::assertSame('check', $tasks[0]['task']->name);
        static::assertSame('sylius:upsun', $tasks[0]['task']->namespace);
    }

    public function testReportsWhenUpsunConfigIsMissing(): void
    {
        $this->runCheck();

        static::assertStringContainsString('No .upsun/config.yaml found.', $this->output->fetch());
    }

    public function testParsesUpsunRegexRuleMappings(): void
    {
        $this->writeUpsunConfig(<<<'YAML'
            applications:
              sylius:
                type: php:8.4
                web:
                  locations:
                    /:
                      rules:
                        ? '\.(css|js)$'
                        :   allow: true
            YAML);

        $this->runCheck();

        static::assertStringContainsString('Found .upsun/config.yaml with 1 application(s): sylius.', $this->output->fetch());
    }

    public function testMySqlApplicationMatchesMySqlUpsunService(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        $this->runCheck('MySQL');

        $output = $this->normalizedOutput();
        static::assertStringContainsString('Symfony application database configuration matches Upsun.', $output);
        static::assertStringContainsString('✓ Local environment MySQL', $output);
        static::assertStringContainsString('✓ Symfony application MySQL', $output);
        static::assertStringContainsString('✓ Upsun MySQL', $output);
    }

    public function testPostgreSqlApplicationMatchesPostgreSqlUpsunService(): void
    {
        $this->writeUpsunConfig(databaseType: 'postgresql:16');
        $this->writeDoctrineConfig("driver: 'pdo_pgsql'");

        $this->runCheck('PostgreSQL');

        $output = $this->normalizedOutput();
        static::assertStringContainsString('Symfony application database configuration matches Upsun.', $output);
    }

    public function testReportsWhenLocalCastorDatabaseDiffersFromDoctrineAndUpsun(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        $this->runCheck('PostgreSQL');

        $output = $this->normalizedOutput();
        static::assertStringContainsString('✗ Local environment PostgreSQL', $output);
        static::assertStringContainsString('✗ Symfony application MySQL', $output);
        static::assertStringContainsString('✓ Upsun MySQL', $output);
        static::assertStringContainsString('Local environment and Symfony application use different database engines', $output);
    }

    public function testPostgreSqlApplicationDoesNotMatchMySqlUpsunService(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("driver: 'pdo_pgsql'");

        $this->runCheck('PostgreSQL');

        $output = $this->normalizedOutput();
        static::assertStringContainsString('The database engines are inconsistent.', $output);
        static::assertStringContainsString('✓ Symfony application PostgreSQL', $output);
        static::assertStringContainsString('✗ Upsun MySQL', $output);
    }

    public function testMySqlApplicationDoesNotMatchPostgreSqlUpsunService(): void
    {
        $this->writeUpsunConfig(databaseType: 'postgresql:16');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        $this->runCheck();

        $output = $this->normalizedOutput();
        static::assertStringContainsString('The database engines are inconsistent.', $output);
        static::assertStringContainsString('Symfony application MySQL', $output);
        static::assertStringContainsString('✗ Upsun PostgreSQL', $output);
    }

    public function testReportsWhenDoctrineDriverCannotBeDetermined(): void
    {
        $this->writeUpsunConfig();
        $this->writeDoctrineConfig('');

        $this->runCheck();

        static::assertStringContainsString('Unable to determine application database driver', $this->output->fetch());
    }

    public function testReportsWhenUpsunDatabaseServiceCannotBeDetermined(): void
    {
        $this->writeUpsunConfig(databaseType: 'redis:7.2');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        $this->runCheck();

        static::assertStringContainsString('Invalid Upsun database configuration (unsupported_service)', $this->output->fetch());
    }

    public function testReportsWhenDatabaseRelationshipIsMissing(): void
    {
        $this->writeUpsunConfig(content: <<<'YAML'
            applications:
              app:
                type: php:8.4
                relationships: {}
            YAML);
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        $this->runCheck();

        $output = $this->normalizedOutput();
        static::assertStringContainsString('Invalid Upsun database configuration (missing_relationship)', $output);
        static::assertStringContainsString('has no database relationship', $output);
    }

    public function testReportsWhenDatabaseRelationshipReferencesMissingService(): void
    {
        $this->writeUpsunConfig(content: <<<'YAML'
            applications:
              app:
                type: php:8.4
                relationships:
                    database: "missing:mysql"
            YAML);
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        $this->runCheck();

        $output = $this->normalizedOutput();
        static::assertStringContainsString('Invalid Upsun database configuration (missing_service)', $output);
        static::assertStringContainsString('service "missing", which is not defined', $output);
    }

    public function testReportsWhenDatabaseEndpointDoesNotMatchServiceType(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8', relationship: 'db:postgresql');
        $this->writeDoctrineConfig("driver: 'pdo_pgsql'");

        $this->runCheck();

        $output = $this->normalizedOutput();
        static::assertStringContainsString('Invalid Upsun database configuration (inconsistent)', $output);
        static::assertStringContainsString('endpoint "postgresql" is incompatible with service "db" of type "mysql:11.8"', $output);
        static::assertStringNotContainsString('database engines are inconsistent', $output);
    }

    public function testUsesEnvironmentSpecificDoctrineDriver(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");
        $this->write(
            'config/packages/doctrine.yaml',
            <<<'YAML'
                doctrine:
                    dbal:
                        driver: 'pdo_mysql'

                when@prod:
                    doctrine:
                        dbal:
                            driver: 'pdo_pgsql'
                YAML,
        );

        $this->runCheck();

        $output = $this->normalizedOutput();
        static::assertStringContainsString('The database engines are inconsistent.', $output);
        static::assertStringContainsString('Symfony application PostgreSQL', $output);
    }

    public function testUsesDoctrineDriverBeforeDatabaseUrl(): void
    {
        $this->writeUpsunConfig(databaseType: 'postgresql:16');
        $this->writeDoctrineConfig("driver: 'pdo_pgsql'\n        url: '%env(resolve:DATABASE_URL)%'");
        $this->write('.env', 'DATABASE_URL=mysql://localhost/app');

        $this->runCheck();

        static::assertStringContainsString('Symfony application PostgreSQL', $this->normalizedOutput());
    }

    public function testDetectsStandardDatabaseUrlDefault(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("url: '%env(resolve:DATABASE_URL)%'");
        $this->write('.env', 'DATABASE_URL=mysql://root@127.0.0.1/sylius?serverVersion=8');

        $this->runCheck();

        $output = $this->normalizedOutput();
        static::assertStringContainsString('Symfony application database configuration matches Upsun.', $output);
    }

    public function testDoesNotInferMessengerWorkerRequirementsFromApplicationConfig(): void
    {
        $this->writeUpsunConfig();
        $this->write('config/packages/messenger.yaml', "framework:\n    messenger:\n        transports:\n            async: 'doctrine://default?queue_name=async'\n");

        $this->runCheck();

        $output = $this->output->fetch();
        static::assertStringContainsString('Found .upsun/config.yaml with 1 application(s): sylius.', $output);
        static::assertStringNotContainsString('Messenger', $output);
        static::assertStringNotContainsString('worker', $output);
    }

    private function runCheck(?string $localDatabaseEngine = null): void
    {
        $tasks = iterator_to_array((new UpsunTasks('app', $this->directory, $localDatabaseEngine))());
        $tasks[0]['function']();
    }

    private function normalizedOutput(): string
    {
        return preg_replace('/\s+/', ' ', $this->output->fetch()) ?? '';
    }

    private function writeUpsunConfig(
        ?string $content = null,
        string $databaseType = 'mysql:11.8',
        ?string $relationship = null,
    ): void {
        $relationship ??= 'db:' . strtok($databaseType, ':');

        $content ??= <<<'YAML'
            applications:
              sylius:
                type: php:8.4
                variables:
                    env:
                        APP_ENV: prod
                relationships:
                    database: "db:MYSQL_ENDPOINT"
            YAML;

        $content = str_replace('db:MYSQL_ENDPOINT', $relationship, $content);

        $content .= "\nservices:\n    db:\n        type: {$databaseType}\n";
        $this->write('.upsun/config.yaml', $content);
    }

    private function writeDoctrineConfig(string $dbal): void
    {
        $this->write('config/packages/doctrine.yaml', "doctrine:\n    dbal:\n        {$dbal}\n");
    }

    private function write(string $file, string $content): void
    {
        $path = $this->directory . '/' . $file;
        $this->filesystem->mkdir(\dirname($path));
        $this->filesystem->dumpFile($path, $content);
    }
}
