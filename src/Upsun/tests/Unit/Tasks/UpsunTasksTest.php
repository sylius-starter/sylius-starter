<?php

declare(strict_types=1);

namespace SyliusStarter\Upsun\Tests\Unit\Tasks;

use Castor\Attribute\AsTask;
use Castor\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SyliusStarter\Upsun\Tasks\UpsunTasks;
use SyliusStarter\Upsun\Util\UpsunCli;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(UpsunTasks::class)]
#[CoversClass(UpsunCli::class)]
final class UpsunTasksTest extends TestCase
{
    private string $directory;

    /**
     * The Sylius application: the temporary directory itself, or its "app" subdirectory in a Git repository.
     */
    private string $applicationDirectory;

    private Filesystem $filesystem;

    private BufferedOutput $output;

    /**
     * @var list<array{string, bool, string}>
     */
    private array $commands = [];

    /**
     * @var array<string, array{int, string}>
     */
    private array $responses = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/upsun_tasks_test_' . uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->directory);
        $this->applicationDirectory = $this->directory;
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

    public function testRegistersCheckAndDeployTasks(): void
    {
        $tasks = iterator_to_array((new UpsunTasks('app', $this->directory))());

        static::assertCount(3, $tasks);

        foreach (['check', 'setup', 'deploy'] as $index => $name) {
            static::assertInstanceOf(AsTask::class, $tasks[$index]['task']);
            static::assertSame($name, $tasks[$index]['task']->name);
            static::assertSame('sylius:upsun', $tasks[$index]['task']->namespace);
        }
    }

    public function testCheckReadsTheConfigurationAtTheGitRoot(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig(databaseType: 'mysql:11.8', sourceRoot: 'app', atRoot: true);
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(0, $this->runCheck('MySQL'));

        static::assertStringContainsString('Symfony application database configuration matches Upsun.', $this->normalizedOutput());
    }

    public function testCheckFailsWhenTheConfigurationIsStillInTheApplicationSubdirectory(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig();

        static::assertSame(1, $this->runCheck());

        static::assertStringContainsString('Run "castor sylius:upsun:setup" to move it there.', $this->normalizedOutput());
    }

    public function testCheckFailsWhenNoApplicationPointsAtTheSubdirectory(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig(sourceRoot: '/', atRoot: true);

        static::assertSame(1, $this->runCheck());

        static::assertStringContainsString('No Upsun application has "source.root: app".', $this->normalizedOutput());
    }

    public function testCheckWarnsAboutAnIgnoredApplicationConfiguration(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig(sourceRoot: 'app', atRoot: true);
        $this->writeUpsunConfig();
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(0, $this->runCheck());

        static::assertStringContainsString('Upsun ignores app/.upsun/config.yaml', $this->normalizedOutput());
    }

    public function testSetupKeepsTheConfigurationOfAnApplicationAtTheGitRoot(): void
    {
        $this->filesystem->mkdir($this->directory . '/.git');
        $this->writeUpsunConfig();

        static::assertSame(0, $this->runSetup());

        static::assertFileExists($this->directory . '/.upsun/config.yaml');
        static::assertStringContainsString('stays where Sylius Standard puts it', $this->normalizedOutput());
    }

    public function testSetupFailsOutsideAGitRepository(): void
    {
        $this->writeUpsunConfig();

        static::assertSame(1, $this->runSetup());

        static::assertStringContainsString('Upsun deploys Git commits.', $this->normalizedOutput());
    }

    public function testSetupMovesTheConfigurationToTheGitRootAndReplacesTheSourceRoot(): void
    {
        $this->useApplicationInSubdirectory();
        $this->write('.upsun/config.yaml', <<<'YAML'
            applications:
                # The Sylius application
                sylius:
                    type: php:8.4
                    web:
                        locations:
                            '/assets/shop':
                                rules:
                                    ? '\.(css|js)$'
                                    :   allow: true
                    hooks:
                        deploy: |
                            set -e

                            bin/console cache:clear
                    source:
                        root: /
            services:
                db:
                    type: mysql:11.8
            YAML);

        static::assertSame(0, $this->runSetup());

        static::assertFileDoesNotExist($this->applicationDirectory . '/.upsun');
        static::assertSame(<<<'YAML'
            applications:
                # The Sylius application
                sylius:
                    type: php:8.4
                    web:
                        locations:
                            '/assets/shop':
                                rules:
                                    ? '\.(css|js)$'
                                    :   allow: true
                    hooks:
                        deploy: |
                            set -e

                            bin/console cache:clear
                    source:
                        root: "app"
            services:
                db:
                    type: mysql:11.8
            YAML, file_get_contents($this->directory . '/.upsun/config.yaml'));
    }

    public function testSetupAddsTheSourceRootWhenMissing(): void
    {
        $this->useApplicationInSubdirectory();
        $this->write('.upsun/config.yaml', "applications:\n  sylius:\n    type: php:8.4\n\nservices:\n  db:\n    type: mysql:11.8\n");

        static::assertSame(0, $this->runSetup());

        static::assertSame(
            "applications:\n  sylius:\n    type: php:8.4\n    source:\n      root: \"app\"\n\nservices:\n  db:\n    type: mysql:11.8\n",
            file_get_contents($this->directory . '/.upsun/config.yaml'),
        );
    }

    public function testSetupAddsTheRootToAnExistingSource(): void
    {
        $this->useApplicationInSubdirectory();
        $this->write('.upsun/config.yaml', "applications:\n    sylius:\n        source:\n            operations: {}\n        type: php:8.4\n");

        static::assertSame(0, $this->runSetup());

        static::assertSame(
            "applications:\n    sylius:\n        source:\n            root: \"app\"\n            operations: {}\n        type: php:8.4\n",
            file_get_contents($this->directory . '/.upsun/config.yaml'),
        );
    }

    public function testSetupThenCheckSucceeds(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig(databaseType: 'mysql:11.8', sourceRoot: '/');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(0, $this->runSetup());
        static::assertSame(0, $this->runCheck('MySQL'));
    }

    public function testSetupIsIdempotent(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig(sourceRoot: 'app', atRoot: true);

        static::assertSame(0, $this->runSetup());

        static::assertStringContainsString('Upsun is already set up', $this->normalizedOutput());
    }

    public function testSetupDoesNotOverwriteAnotherConfigurationAtTheGitRoot(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig(sourceRoot: 'backend', atRoot: true);
        $this->writeUpsunConfig();

        static::assertSame(1, $this->runSetup());

        static::assertFileExists($this->applicationDirectory . '/.upsun/config.yaml');
        static::assertStringContainsString('set "source.root: app" on the Sylius application by hand', $this->normalizedOutput());
    }

    public function testSetupKeepsOtherFilesOfTheApplicationUpsunDirectory(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig();
        $this->write('.upsun/local/project.yaml', 'id: abc');

        static::assertSame(0, $this->runSetup());

        static::assertFileExists($this->applicationDirectory . '/.upsun/local/project.yaml');
        static::assertStringContainsString('app/.upsun/ holds other files', $this->normalizedOutput());
    }

    public function testReportsWhenUpsunConfigIsMissing(): void
    {
        static::assertSame(1, $this->runCheck());

        static::assertStringContainsString('No .upsun/config.yaml found.', $this->output->fetch());
    }

    public function testCheckSucceedsWhenTheConfigurationMatches(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(0, $this->runCheck('MySQL'));
    }

    public function testCheckFailsWhenApplicationAndUpsunEnginesDiffer(): void
    {
        $this->writeUpsunConfig(databaseType: 'postgresql:16');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(1, $this->runCheck());
    }

    public function testCheckOnlyWarnsWhenTheLocalEngineDiffers(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(0, $this->runCheck('PostgreSQL'));
        static::assertStringContainsString('The database engines are inconsistent.', $this->output->fetch());
    }

    public function testCheckOnlyWarnsWhenAnEngineCannotBeDetermined(): void
    {
        $this->writeUpsunConfig();
        $this->writeDoctrineConfig('');

        static::assertSame(0, $this->runCheck());
    }

    public function testCheckFailsOnAnInvalidDatabaseRelationship(): void
    {
        $this->writeUpsunConfig(databaseType: 'redis:7.2');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(1, $this->runCheck());
    }

    public function testDeployStopsWhenTheCheckFails(): void
    {
        static::assertSame(1, $this->runDeploy());

        static::assertStringContainsString('Fix the Upsun configuration before deploying.', $this->output->fetch());
        static::assertSame([], $this->commands);
    }

    public function testDeployFailsWithoutCli(): void
    {
        $this->writeDeployableApplication();

        static::assertSame(1, $this->runDeploy(cli: false));

        static::assertStringContainsString('Neither the Symfony CLI nor the Upsun CLI was found.', $this->normalizedOutput());
    }

    public function testDeployFailsOutsideAGitRepository(): void
    {
        $this->writeDeployableApplication();
        $this->responses['git rev-parse --show-toplevel'] = [128, ''];

        static::assertSame(1, $this->runDeploy());

        static::assertStringContainsString('Upsun deploys Git commits.', $this->normalizedOutput());
    }

    public function testDeployPushesFromTheGitRootWhenTheApplicationIsInASubdirectory(): void
    {
        $this->useApplicationInSubdirectory();
        $this->writeUpsunConfig(databaseType: 'mysql:11.8', sourceRoot: 'app', atRoot: true);
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");

        static::assertSame(0, $this->runDeploy());

        static::assertSame($this->applicationDirectory, $this->commands[0][2]);
        static::assertSame(['symfony cloud:deploy', $this->directory], [$this->commands[4][0], $this->commands[4][2]]);
    }

    public function testDeployFailsWhenNotLoggedIn(): void
    {
        $this->writeDeployableApplication();
        $this->responses['symfony cloud:auth:info --no-interaction'] = [3, ''];

        static::assertSame(1, $this->runDeploy());

        static::assertStringContainsString('You are not logged in to Upsun. Run "symfony cloud:login" first.', $this->normalizedOutput());
    }

    public function testDeployFailsWhenNoProjectIsLinked(): void
    {
        $this->writeDeployableApplication();
        $this->responses['symfony cloud:project:info --no-interaction'] = [1, ''];

        static::assertSame(1, $this->runDeploy());

        $output = $this->normalizedOutput();
        static::assertStringContainsString('No Upsun project is linked to this Git repository.', $output);
        static::assertStringContainsString('symfony cloud:set-remote <project-id>', $output);
    }

    public function testDeployPushesTheCommittedCode(): void
    {
        $this->writeDeployableApplication();

        static::assertSame(0, $this->runDeploy());

        static::assertSame([
            'git rev-parse --show-toplevel',
            'symfony cloud:auth:info --no-interaction',
            'symfony cloud:project:info --no-interaction',
            'git status --porcelain',
            'symfony cloud:deploy',
        ], $this->commandLines());
        static::assertSame([false, false, false, false, true], array_column($this->commands, 1));
        static::assertStringContainsString('Deployed to Upsun.', $this->output->fetch());
    }

    public function testDeployPassesTheProjectEnvironmentAndActivation(): void
    {
        $this->writeDeployableApplication();

        static::assertSame(0, $this->runDeploy(environment: 'staging', project: 'abc123', activate: true));

        static::assertContains('symfony cloud:project:info --project=abc123 --no-interaction', $this->commandLines());
        static::assertContains('symfony cloud:deploy --project=abc123 --environment=staging --activate', $this->commandLines());
    }

    public function testDeployWarnsAboutUncommittedChanges(): void
    {
        $this->writeDeployableApplication();
        $this->responses['git status --porcelain'] = [0, " M composer.json\n"];

        static::assertSame(0, $this->runDeploy());

        static::assertStringContainsString('uncommitted changes', $this->normalizedOutput());
    }

    public function testDeployReportsAFailedPush(): void
    {
        $this->writeDeployableApplication();
        $this->responses['symfony cloud:deploy'] = [1, ''];

        static::assertSame(1, $this->runDeploy());

        static::assertStringContainsString('The Upsun deployment failed.', $this->output->fetch());
    }

    public function testUpsunCliUsesItsOwnCommandNames(): void
    {
        $this->writeDeployableApplication();

        static::assertSame(0, $this->runDeploy(binary: 'upsun'));

        static::assertContains('upsun auth:info --no-interaction', $this->commandLines());
        static::assertContains('upsun push', $this->commandLines());
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

    private function runCheck(?string $localDatabaseEngine = null): int
    {
        $tasks = iterator_to_array((new UpsunTasks('app', $this->applicationDirectory, $localDatabaseEngine))());

        return $tasks[0]['function']();
    }

    private function runDeploy(
        ?string $environment = null,
        ?string $project = null,
        bool $activate = false,
        bool $cli = true,
        string $binary = 'symfony',
    ): int {
        $executor = function (array $command, string $directory, bool $interactive): array {
            $line = implode(' ', $command);
            $this->commands[] = [$line, $interactive, $directory];

            // By default, the application is the root of a clean Git repository and every command succeeds.
            return $this->responses[$line] ?? ('git rev-parse --show-toplevel' === $line ? [0, $this->directory . "\n"] : [0, '']);
        };
        $factory = static fn(): ?UpsunCli => $cli ? new UpsunCli($binary, $executor) : null;
        $tasks = iterator_to_array((new UpsunTasks('app', $this->applicationDirectory, 'MySQL', $factory))());

        return $tasks[2]['function']($environment, $project, $activate);
    }

    private function runSetup(): int
    {
        $tasks = iterator_to_array((new UpsunTasks('app', $this->applicationDirectory))());

        return $tasks[1]['function']();
    }

    private function useApplicationInSubdirectory(): void
    {
        $this->filesystem->mkdir([$this->directory . '/.git', $this->directory . '/app']);
        // Upsun::repositoryRoot() resolves symbolic links (/var → /private/var on macOS).
        $this->directory = (string) realpath($this->directory);
        $this->applicationDirectory = $this->directory . '/app';
    }

    /**
     * @return list<string>
     */
    private function commandLines(): array
    {
        return array_column($this->commands, 0);
    }

    private function writeDeployableApplication(): void
    {
        $this->writeUpsunConfig(databaseType: 'mysql:11.8');
        $this->writeDoctrineConfig("driver: 'pdo_mysql'");
    }

    private function normalizedOutput(): string
    {
        return preg_replace('/\s+/', ' ', $this->output->fetch()) ?? '';
    }

    private function writeUpsunConfig(
        ?string $content = null,
        string $databaseType = 'mysql:11.8',
        ?string $relationship = null,
        ?string $sourceRoot = null,
        bool $atRoot = false,
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

        if (null !== $sourceRoot) {
            $content .= "\n    source:\n        root: \"{$sourceRoot}\"";
        }

        $content .= "\nservices:\n    db:\n        type: {$databaseType}\n";
        $atRoot
            ? $this->filesystem->dumpFile($this->directory . '/.upsun/config.yaml', $content)
            : $this->write('.upsun/config.yaml', $content);
    }

    private function writeDoctrineConfig(string $dbal): void
    {
        $this->write('config/packages/doctrine.yaml', "doctrine:\n    dbal:\n        {$dbal}\n");
    }

    private function write(string $file, string $content): void
    {
        $path = $this->applicationDirectory . '/' . $file;
        $this->filesystem->mkdir(\dirname($path));
        $this->filesystem->dumpFile($path, $content);
    }
}
