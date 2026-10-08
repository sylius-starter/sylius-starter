<?php

declare(strict_types=1);

namespace Castor\Sylius\Tasks;

use Castor\Attribute\AsTask;
use Castor\Sylius\App;
use Castor\Sylius\Util\Upsun;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

use function Castor\io;

final class UpsunTasks
{
    public function __construct(
        private readonly string $name,
        private readonly string $directory,
        private readonly ?string $localDatabaseEngine = null,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);
        $localDatabaseEngine = $this->localDatabaseEngine;

        yield [
            'task' => new AsTask('check', 'sylius:upsun', 'Check Upsun deployment configuration'),
            'function' => static function () use ($app, $localDatabaseEngine): void {
                self::check($app, $localDatabaseEngine);
            },
        ];
    }

    private static function check(App $app, ?string $localDatabaseEngine): void
    {
        io()->title('Checking Upsun configuration');

        $configFile = $app->directory() . '/.upsun/config.yaml';

        if (!is_file($configFile)) {
            io()->error('No .upsun/config.yaml found. Sylius Standard provides this configuration; restore it rather than generating a replacement.');

            return;
        }

        try {
            $config = Upsun::parseConfig($app);
        } catch (ParseException $exception) {
            io()->error(\sprintf('Could not parse .upsun/config.yaml: %s', $exception->getMessage()));

            return;
        }

        if (!\is_array($config) || !isset($config['applications']) || !\is_array($config['applications']) || [] === $config['applications']) {
            io()->error('.upsun/config.yaml must define at least one application under "applications".');

            return;
        }

        foreach ($config['applications'] as $applicationName => $application) {
            if (!\is_string($applicationName) || !\is_array($application)) {
                io()->error('Each entry under "applications" must map an application name to its configuration.');

                return;
            }
        }

        io()->success(\sprintf(
            'Found .upsun/config.yaml with %d application(s): %s.',
            \count($config['applications']),
            implode(', ', array_keys($config['applications'])),
        ));

        try {
            $applicationEngine = self::applicationDatabaseEngine($app, $config);
        } catch (ParseException|FormatException $exception) {
            io()->warning(\sprintf('Unable to determine application database driver: %s', $exception->getMessage()));

            return;
        }

        $upsunDatabase = Upsun::databaseConfiguration($config, $app->name());

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

            return;
        }

        self::reportDatabaseConfiguration(
            $localDatabaseEngine,
            $applicationEngine,
            $upsunDatabase['engine'],
        );
    }

    private static function reportDatabaseConfiguration(
        ?string $localEngine,
        ?string $applicationEngine,
        ?string $upsunEngine,
    ): void {
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

            return;
        }

        if (null !== $applicationEngine && null !== $upsunEngine) {
            io()->success('Symfony application database configuration matches Upsun.');
        }
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
    private static function applicationDatabaseEngine(App $app, array $upsunConfig): ?string
    {
        $environment = self::applicationEnvironment($app, $upsunConfig);
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
    private static function applicationEnvironment(App $app, array $upsunConfig): string
    {
        $applications = $upsunConfig['applications'] ?? [];
        $application = $applications[$app->name()] ?? (1 === \count($applications) ? reset($applications) : null);
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
