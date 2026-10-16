<?php

declare(strict_types=1);

namespace SyliusStarter\Upsun\Tasks;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Util\Runner;
use SyliusStarter\Upsun\Util\Upsun;
use SyliusStarter\Upsun\Util\UpsunCli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

use function Castor\fs;
use function Castor\io;

final class UpsunTasks
{
    public function __construct(
        private readonly string $name,
        private readonly string $directory,
        private readonly ?string $localDatabaseEngine = null,
        /** @var ?\Closure(): ?UpsunCli */
        private readonly ?\Closure $cli = null,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);
        $localDatabaseEngine = $this->localDatabaseEngine;
        $cli = $this->cli ?? static fn(): ?UpsunCli => UpsunCli::detect();

        yield [
            'task' => new AsTask('check', 'sylius:upsun', 'Check Upsun deployment configuration'),
            'function' => static fn(): int => self::check($app, $localDatabaseEngine) ? Command::SUCCESS : Command::FAILURE,
        ];

        yield [
            'task' => new AsTask('setup', 'sylius:upsun', 'Move .upsun/ to the root of the Git repository when the application is in a subdirectory'),
            'function' => static fn(): int => self::setup($app),
        ];

        yield [
            'task' => new AsTask('deploy', 'sylius:upsun', 'Check the Upsun configuration, then deploy the committed code to Upsun'),
            'function' => static fn(
                #[AsOption(description: 'Upsun environment to deploy (defaults to the current Git branch)')]
                ?string $environment = null,
                #[AsOption(description: 'Upsun project ID (defaults to the project linked to the Git repository)')]
                ?string $project = null,
                #[AsOption(description: 'Activate the environment if it is not active yet')]
                bool $activate = false,
            ): int => self::deploy($app, $localDatabaseEngine, $cli(), $environment, $project, $activate),
        ];
    }

    private static function deploy(
        App $app,
        ?string $localDatabaseEngine,
        ?UpsunCli $cli,
        ?string $environment,
        ?string $project,
        bool $activate,
    ): int {
        if (!self::check($app, $localDatabaseEngine)) {
            io()->error('Fix the Upsun configuration before deploying.');

            return Command::FAILURE;
        }

        io()->title('Deploying to Upsun');

        if (null === $cli) {
            io()->error('Neither the Symfony CLI nor the Upsun CLI was found. Install the Symfony CLI: https://symfony.com/download');

            return Command::FAILURE;
        }

        $applicationDirectory = Runner::localAppDirectory($app);
        [$exitCode, $root] = $cli->git(['rev-parse', '--show-toplevel'], $applicationDirectory);

        if (0 !== $exitCode) {
            io()->error(\sprintf('%s is not in a Git repository: Upsun deploys Git commits.', $applicationDirectory));

            return Command::FAILURE;
        }

        // Upsun receives the whole repository: the check made sure .upsun/config.yaml is at its root.
        $directory = trim($root);

        if (!$cli->succeeds('auth:info', $directory, ['--no-interaction'])) {
            io()->error(\sprintf('You are not logged in to Upsun. Run "%s" first.', $cli->commandLine('login')));

            return Command::FAILURE;
        }

        $projectArguments = null !== $project && '' !== $project ? ['--project=' . $project] : [];

        if (!$cli->succeeds('project:info', $directory, [...$projectArguments, '--no-interaction'])) {
            io()->error(null === $project || '' === $project
                ? \sprintf(
                    'No Upsun project is linked to this Git repository. Run "%s <project-id>", create a project with "%s", or pass --project.',
                    $cli->commandLine('set-remote'),
                    $cli->commandLine('create'),
                )
                : \sprintf('Upsun project "%s" was not found, or you cannot access it.', $project));

            return Command::FAILURE;
        }

        [, $changes] = $cli->git(['status', '--porcelain'], $directory);

        if ('' !== trim($changes)) {
            io()->warning('The Git working tree has uncommitted changes: Upsun only receives committed code.');
        }

        $arguments = $projectArguments;

        if (null !== $environment && '' !== $environment) {
            $arguments[] = '--environment=' . $environment;
        }

        if ($activate) {
            $arguments[] = '--activate';
        }

        io()->comment(\sprintf('Running "%s".', implode(' ', [$cli->commandLine('push'), ...$arguments])));

        if (0 !== $cli->passthru('push', $directory, $arguments)) {
            io()->error('The Upsun deployment failed.');

            return Command::FAILURE;
        }

        io()->success('Deployed to Upsun.');

        return Command::SUCCESS;
    }

    private static function setup(App $app): int
    {
        io()->title('Setting up Upsun');

        $applicationDirectory = Runner::localAppDirectory($app);
        $repositoryRoot = Upsun::repositoryRoot($applicationDirectory);

        if (null === $repositoryRoot) {
            io()->error(\sprintf('%s is not in a Git repository: Upsun deploys Git commits. Run "git init" first.', $applicationDirectory));

            return Command::FAILURE;
        }

        $sourceRoot = Upsun::sourceRoot($applicationDirectory);

        if ('' === $sourceRoot) {
            io()->success('The Sylius application is the root of its Git repository: .upsun/config.yaml stays where Sylius Standard puts it.');

            return Command::SUCCESS;
        }

        $rootConfigFile = Upsun::configFile($repositoryRoot);
        $applicationConfigFile = Upsun::configFile($applicationDirectory);

        if (is_file($rootConfigFile)) {
            try {
                $config = Upsun::parseConfig($rootConfigFile);
            } catch (ParseException $exception) {
                io()->error(\sprintf('Could not parse .upsun/config.yaml: %s', $exception->getMessage()));

                return Command::FAILURE;
            }

            if (!\is_array($config) || null === Upsun::applicationName($config, $sourceRoot, $app->name())) {
                io()->error(\sprintf(
                    'A .upsun/config.yaml already exists at the root of the Git repository: set "source.root: %s" on the Sylius application by hand.',
                    $sourceRoot,
                ));

                return Command::FAILURE;
            }

            if (is_file($applicationConfigFile)) {
                io()->warning(\sprintf('Upsun ignores %s/.upsun/config.yaml: only the one at the root of the Git repository is used. Remove it.', $sourceRoot));
            }

            io()->success(\sprintf('Upsun is already set up: .upsun/config.yaml is at the root of the Git repository with "source.root: %s".', $sourceRoot));

            return Command::SUCCESS;
        }

        if (!is_file($applicationConfigFile)) {
            io()->error('No .upsun/config.yaml found. Sylius Standard provides this configuration; restore it rather than generating a replacement.');

            return Command::FAILURE;
        }

        try {
            $config = Upsun::parseConfig($applicationConfigFile);
            $applicationName = \is_array($config) ? Upsun::applicationName($config, '', $app->name()) : null;

            if (null === $applicationName) {
                throw new \RuntimeException(\sprintf('No Upsun application is named "%s", and there are several.', $app->name()));
            }

            $contents = Upsun::withSourceRoot((string) file_get_contents($applicationConfigFile), $applicationName, $sourceRoot);
            $updatedConfig = Upsun::parseContents($contents);

            if (!\is_array($updatedConfig) || $applicationName !== Upsun::applicationName($updatedConfig, $sourceRoot, $app->name())) {
                throw new \RuntimeException(\sprintf('Could not set "source.root: %s" on application "%s".', $sourceRoot, $applicationName));
            }
        } catch (ParseException|\RuntimeException $exception) {
            io()->error(\sprintf('Could not adapt .upsun/config.yaml: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        fs()->dumpFile($rootConfigFile, $contents);
        fs()->remove($applicationConfigFile);

        $applicationUpsunDirectory = \dirname($applicationConfigFile);

        if ([] === array_diff((array) scandir($applicationUpsunDirectory), ['.', '..'])) {
            fs()->remove($applicationUpsunDirectory);
        } else {
            io()->warning(\sprintf('%s/.upsun/ holds other files: move them to .upsun/ at the root of the Git repository.', $sourceRoot));
        }

        io()->success(\sprintf(
            'Moved .upsun/config.yaml to the root of the Git repository, with "source.root: %s" on application "%s". Commit it, then run "castor sylius:upsun:check".',
            $sourceRoot,
            $applicationName,
        ));

        return Command::SUCCESS;
    }

    /**
     * Returns false when the configuration cannot be deployed as is; an engine that cannot be determined is only a warning.
     */
    private static function check(App $app, ?string $localDatabaseEngine): bool
    {
        io()->title('Checking Upsun configuration');

        $applicationDirectory = Runner::localAppDirectory($app);
        $sourceRoot = Upsun::sourceRoot($applicationDirectory);
        // Upsun reads the configuration at the root of the Git repository.
        $configFile = Upsun::configFile('' === $sourceRoot ? $applicationDirectory : (string) Upsun::repositoryRoot($applicationDirectory));
        $applicationConfigFile = Upsun::configFile($applicationDirectory);

        if (!is_file($configFile)) {
            if (is_file($applicationConfigFile)) {
                io()->error(\sprintf(
                    'The application\'s .upsun/config.yaml is in %s/, but Upsun reads it at the root of the Git repository. Run "castor sylius:upsun:setup" to move it there.',
                    $sourceRoot,
                ));

                return false;
            }

            io()->error('No .upsun/config.yaml found. Sylius Standard provides this configuration; restore it rather than generating a replacement.');

            return false;
        }

        try {
            $config = Upsun::parseConfig($configFile);
        } catch (ParseException $exception) {
            io()->error(\sprintf('Could not parse .upsun/config.yaml: %s', $exception->getMessage()));

            return false;
        }

        if (!\is_array($config) || !isset($config['applications']) || !\is_array($config['applications']) || [] === $config['applications']) {
            io()->error('.upsun/config.yaml must define at least one application under "applications".');

            return false;
        }

        foreach ($config['applications'] as $applicationName => $application) {
            if (!\is_string($applicationName) || !\is_array($application)) {
                io()->error('Each entry under "applications" must map an application name to its configuration.');

                return false;
            }
        }

        io()->success(\sprintf(
            'Found .upsun/config.yaml with %d application(s): %s.',
            \count($config['applications']),
            implode(', ', array_keys($config['applications'])),
        ));

        $applicationName = Upsun::applicationName($config, $sourceRoot, $app->name());

        if (null === $applicationName) {
            io()->error('' === $sourceRoot
                ? \sprintf('No Upsun application is named "%s", and there are several: name it after the Sylius application.', $app->name())
                : \sprintf('No Upsun application has "source.root: %s". Run "castor sylius:upsun:setup", or set it on the Sylius application.', $sourceRoot));

            return false;
        }

        if ('' !== $sourceRoot && is_file($applicationConfigFile)) {
            io()->warning(\sprintf('Upsun ignores %s/.upsun/config.yaml: only the one at the root of the Git repository is used. Remove it.', $sourceRoot));
        }

        try {
            $applicationEngine = self::applicationDatabaseEngine($app, $config, $applicationName);
        } catch (ParseException|FormatException $exception) {
            io()->warning(\sprintf('Unable to determine application database driver: %s', $exception->getMessage()));

            return true;
        }

        $upsunDatabase = Upsun::databaseConfiguration($config, $applicationName);

        if ('valid' !== $upsunDatabase['status']) {
            io()->section('Database');
            io()->writeln(self::databaseStatusLine(
                'Local environment',
                $localDatabaseEngine,
                null === $localDatabaseEngine ? '?' : '✓',
            ));
            io()->writeln(self::databaseStatusLine(
                'Symfony application',
                $applicationEngine,
                null === $applicationEngine ? '?' : '✓',
            ));
            io()->writeln(self::databaseStatusLine('Upsun', null, '✗'));
            io()->error(\sprintf(
                'Invalid Upsun database configuration (%s): %s',
                $upsunDatabase['status'],
                $upsunDatabase['message'] ?? 'Unable to validate the database relationship.',
            ));

            return false;
        }

        return self::reportDatabaseConfiguration(
            $localDatabaseEngine,
            $applicationEngine,
            $upsunDatabase['engine'],
        );
    }

    private static function reportDatabaseConfiguration(
        ?string $localEngine,
        ?string $applicationEngine,
        ?string $upsunEngine,
    ): bool {
        io()->section('Database');
        io()->writeln(self::databaseStatusLine(
            'Local environment',
            $localEngine,
            null === $localEngine ? '?' : (null !== $applicationEngine && $localEngine !== $applicationEngine ? '✗' : '✓'),
        ));
        io()->writeln(self::databaseStatusLine(
            'Symfony application',
            $applicationEngine,
            null === $applicationEngine ? '?' : (null !== $localEngine && $localEngine !== $applicationEngine ? '✗' : '✓'),
        ));
        io()->writeln(self::databaseStatusLine(
            'Upsun',
            $upsunEngine,
            null === $upsunEngine ? '?' : (null !== $applicationEngine && $upsunEngine !== $applicationEngine ? '✗' : '✓'),
        ));

        if (null === $applicationEngine) {
            io()->warning('Unable to determine application database driver from Doctrine DBAL configuration (expected MySQL or PostgreSQL).');
        }

        if (null === $upsunEngine) {
            io()->warning('Unable to determine the Upsun database service from .upsun/config.yaml (expected MySQL or PostgreSQL).');
        }

        $mismatches = [];

        if (null !== $localEngine && null !== $applicationEngine && $localEngine !== $applicationEngine) {
            $mismatches[] = \sprintf('Local environment and Symfony application use different database engines (%s vs %s).', $localEngine, $applicationEngine);
        }

        if (null !== $applicationEngine && null !== $upsunEngine && $applicationEngine !== $upsunEngine) {
            $mismatches[] = \sprintf('Symfony application and Upsun use different database engines (%s vs %s).', $applicationEngine, $upsunEngine);
        }

        if ([] !== $mismatches) {
            io()->warning("The database engines are inconsistent.\n" . implode("\n", $mismatches) . "\nAlign the database configuration before deploying.");

            // Only the application and Upsun engines matter once deployed: a different local engine stays a warning.
            return null === $applicationEngine || null === $upsunEngine || $applicationEngine === $upsunEngine;
        }

        if (null !== $applicationEngine && null !== $upsunEngine) {
            io()->success('Symfony application database configuration matches Upsun.');
        }

        return true;
    }

    private static function databaseStatusLine(string $label, ?string $engine, string $status): string
    {
        return \sprintf(
            '%s %-22s %s',
            $status,
            $label,
            $engine ?? 'Unable to determine',
        );
    }

    /**
     * @param array<string, mixed> $upsunConfig
     */
    private static function applicationDatabaseEngine(App $app, array $upsunConfig, string $applicationName): ?string
    {
        $environment = self::applicationEnvironment($app, $upsunConfig, $applicationName);
        $configuration = [];
        $packagesPath = $app->directory() . '/config/packages';
        $files = [
            $packagesPath . '/doctrine.yaml',
            $packagesPath . '/doctrine.yml',
            $packagesPath . '/' . $environment . '/doctrine.yaml',
            $packagesPath . '/' . $environment . '/doctrine.yml',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $parsed = SymfonyYaml::parseFile($file, SymfonyYaml::PARSE_CUSTOM_TAGS);

            if (!\is_array($parsed)) {
                continue;
            }

            if (\is_array($parsed['doctrine']['dbal'] ?? null)) {
                $configuration = array_replace_recursive($configuration, $parsed['doctrine']['dbal']);
            }

            $environmentConfiguration = $parsed['when@' . $environment]['doctrine']['dbal'] ?? null;

            if (\is_array($environmentConfiguration)) {
                $configuration = array_replace_recursive($configuration, $environmentConfiguration);
            }
        }

        if ([] === $configuration) {
            return null;
        }

        $defaultConnection = $configuration['default_connection'] ?? 'default';
        $connection = \is_string($defaultConnection)
            ? ($configuration['connections'][$defaultConnection] ?? $configuration)
            : $configuration;

        if (!\is_array($connection)) {
            return null;
        }

        if (\is_string($connection['driver'] ?? null)) {
            return self::engineFromDriver($connection['driver']);
        }

        $url = $connection['url'] ?? null;

        if (!\is_string($url)) {
            return null;
        }

        if (1 === preg_match('/^%env\((?:resolve:)?([A-Z0-9_]+)\)%$/', $url, $matches)) {
            $url = self::environmentVariables($app, $environment)[$matches[1]] ?? null;
        }

        return \is_string($url) ? self::engineFromUrl($url) : null;
    }

    /**
     * @param array<string, mixed> $upsunConfig
     */
    private static function applicationEnvironment(App $app, array $upsunConfig, string $applicationName): string
    {
        $application = $upsunConfig['applications'][$applicationName] ?? null;
        $configuredEnvironment = \is_array($application)
            ? ($application['variables']['env']['APP_ENV'] ?? null)
            : null;

        if (\is_string($configuredEnvironment) && '' !== $configuredEnvironment) {
            return $configuredEnvironment;
        }

        $variables = self::environmentVariables($app, 'dev');

        return \is_string($variables['APP_ENV'] ?? null) && '' !== $variables['APP_ENV']
            ? $variables['APP_ENV']
            : 'dev';
    }

    /**
     * @return array<string, string>
     */
    private static function environmentVariables(App $app, string $environment): array
    {
        $directory = $app->directory();
        $files = [
            $directory . '/.env',
            $directory . '/.env.local',
            $directory . '/.env.' . $environment,
            $directory . '/.env.' . $environment . '.local',
        ];
        $variables = [];
        $dotenv = new Dotenv();

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $variables = array_replace($variables, $dotenv->parse((string) file_get_contents($file), $file));
        }

        return $variables;
    }

    private static function engineFromDriver(string $driver): ?string
    {
        return match (strtolower($driver)) {
            'pdo_mysql', 'mysqli', 'mysql' => 'MySQL',
            'pdo_pgsql', 'pgsql', 'postgres', 'postgresql' => 'PostgreSQL',
            default => null,
        };
    }

    private static function engineFromUrl(string $url): ?string
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);

        return \is_string($scheme) ? self::engineFromDriver($scheme) : null;
    }

}
