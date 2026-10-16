<?php

declare(strict_types=1);

namespace SyliusStarter\Upsun\Util;

use Symfony\Component\Yaml\Yaml as SymfonyYaml;

final readonly class Upsun
{
    public static function configFile(string $directory): string
    {
        return $directory . '/.upsun/config.yaml';
    }

    /**
     * The closest directory holding a ".git" entry (a directory, or a file in a worktree), or null outside Git.
     */
    public static function repositoryRoot(string $directory): ?string
    {
        $directory = realpath($directory);

        while (false !== $directory) {
            if (file_exists($directory . '/.git')) {
                return $directory;
            }

            $parent = \dirname($directory);

            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }

        return null;
    }

    /**
     * The application directory relative to the Git repository root, as Upsun expects it in "source.root":
     * "" when the application is the root of its repository, or outside Git.
     */
    public static function sourceRoot(string $applicationDirectory): string
    {
        $repositoryRoot = self::repositoryRoot($applicationDirectory);
        $applicationDirectory = realpath($applicationDirectory);

        if (null === $repositoryRoot || false === $applicationDirectory || $repositoryRoot === $applicationDirectory) {
            return '';
        }

        return substr($applicationDirectory, \strlen($repositoryRoot) + 1);
    }

    /**
     * The Upsun application serving the Sylius application: the one whose "source.root" points at it when the
     * configuration sits at the root of a repository holding the application in a subdirectory, otherwise the one named
     * after the application, or the only one.
     *
     * @param array<mixed> $config
     */
    public static function applicationName(array $config, string $sourceRoot, string $name): ?string
    {
        $applications = \is_array($config['applications'] ?? null) ? $config['applications'] : [];

        if ('' !== $sourceRoot) {
            foreach ($applications as $applicationName => $application) {
                $root = \is_array($application) ? ($application['source']['root'] ?? '') : '';

                if (\is_string($applicationName) && \is_string($root) && trim($root, '/') === $sourceRoot) {
                    return $applicationName;
                }
            }

            return null;
        }

        if (isset($applications[$name])) {
            return $name;
        }

        $names = array_keys($applications);

        return 1 === \count($names) && \is_string($names[0]) ? $names[0] : null;
    }

    /**
     * Sets "source.root" on an application by editing the YAML as text: dumping the parsed configuration would drop
     * the comments of Sylius Standard's file and its explicit "? '…'" keys.
     */
    public static function withSourceRoot(string $contents, string $applicationName, string $root): string
    {
        $lines = preg_split('/\R/', $contents);

        if (false === $lines) {
            throw new \RuntimeException('Could not read the Upsun configuration lines.');
        }

        $applications = self::findLine($lines, 0, \count($lines), '/^applications:\s*(#.*)?$/');
        $application = null === $applications ? null : self::findLine(
            $lines,
            $applications + 1,
            self::blockEnd($lines, $applications, 0),
            '/^[ \t]+["\x27]?' . preg_quote($applicationName, '/') . '["\x27]?:\s*(#.*)?$/',
        );

        if (null === $application) {
            throw new \RuntimeException(\sprintf('Application "%s" was not found under "applications".', $applicationName));
        }

        $applicationIndent = self::indent($lines[$application]);
        $applicationEnd = self::blockEnd($lines, $application, $applicationIndent);
        $childIndent = $applicationIndent + 4;

        for ($i = $application + 1; $i < $applicationEnd; ++$i) {
            if (!self::isBlank($lines[$i])) {
                $childIndent = self::indent($lines[$i]);

                break;
            }
        }

        $step = $childIndent - $applicationIndent;
        $rootLine = str_repeat(' ', $childIndent + $step) . 'root: "' . $root . '"';
        $source = self::findLine($lines, $application + 1, $applicationEnd, '/^' . str_repeat(' ', $childIndent) . 'source:\s*(#.*)?$/');

        if (null === $source) {
            // After the last line of the application, before the blank lines separating it from what follows.
            $insertAt = $applicationEnd;

            while ($insertAt > $application + 1 && self::isBlank($lines[$insertAt - 1])) {
                --$insertAt;
            }

            array_splice($lines, $insertAt, 0, [str_repeat(' ', $childIndent) . 'source:', $rootLine]);

            return implode("\n", $lines);
        }

        $sourceEnd = self::blockEnd($lines, $source, $childIndent);
        $existingRoot = self::findLine($lines, $source + 1, $sourceEnd, '/^[ \t]+root:/');

        if (null === $existingRoot) {
            array_splice($lines, $source + 1, 0, [$rootLine]);
        } else {
            $lines[$existingRoot] = str_repeat(' ', self::indent($lines[$existingRoot])) . 'root: "' . $root . '"';
        }

        return implode("\n", $lines);
    }

    /**
     * @return mixed
     */
    public static function parseConfig(string $file): mixed
    {
        $contents = file_get_contents($file);

        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Could not read "%s".', $file));
        }

        return self::parseContents($contents);
    }

    /**
     * @return mixed
     */
    public static function parseContents(string $contents): mixed
    {
        // Upsun uses explicit scalar keys for regex rules, which Symfony YAML does not parse.
        $contents = preg_replace_callback(
            '/^(?<indent>[ \t]*)\? (?<key>\x27[^\x27]*\x27)\R(?P=indent):[ \t]*(?<value>.+)$/m',
            static fn(array $matches): string => $matches['indent'] . $matches['key'] . ":\n" . $matches['indent'] . '  ' . $matches['value'],
            $contents,
        );

        if (null === $contents) {
            throw new \RuntimeException('Could not normalize Upsun YAML mapping keys.');
        }

        return SymfonyYaml::parse($contents, SymfonyYaml::PARSE_CUSTOM_TAGS);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{status: 'valid'|'missing_relationship'|'invalid_relationship'|'missing_service'|'unsupported_service'|'inconsistent', engine: ?string, message: ?string}
     */
    public static function databaseConfiguration(array $config, string $applicationName): array
    {
        $applications = $config['applications'] ?? [];
        $application = \is_array($applications)
            ? ($applications[$applicationName] ?? (1 === \count($applications) ? reset($applications) : null))
            : null;

        if (!\is_array($application)) {
            return self::databaseResult('missing_relationship', null, 'No matching Upsun application was found.');
        }

        $relationships = $application['relationships'] ?? [];
        $relationship = \is_array($relationships) ? ($relationships['database'] ?? null) : null;

        if (null === $relationship || '' === $relationship) {
            return self::databaseResult('missing_relationship', null, 'The Upsun application has no database relationship.');
        }

        if (!\is_string($relationship) || 1 !== preg_match('/^([A-Za-z0-9_-]+):([A-Za-z0-9_-]+)$/', $relationship, $matches)) {
            return self::databaseResult('invalid_relationship', null, 'The database relationship must reference a service and endpoint, for example "db:mysql".');
        }

        [, $serviceName, $endpoint] = $matches;
        $services = $config['services'] ?? [];

        if (!\is_array($services)) {
            return self::databaseResult('missing_service', null, \sprintf('The database relationship references service "%s", but no services are defined.', $serviceName));
        }

        $service = $services[$serviceName] ?? null;

        if (!\is_array($service)) {
            return self::databaseResult('missing_service', null, \sprintf('The database relationship references service "%s", which is not defined under "services".', $serviceName));
        }

        $type = $service['type'] ?? null;

        if (!\is_string($type) || null === ($serviceEngine = self::engineFromType($type))) {
            return self::databaseResult('unsupported_service', null, \sprintf('Upsun service "%s" does not declare a supported MySQL or PostgreSQL type.', $serviceName));
        }

        $endpointEngine = self::engineFromEndpoint($endpoint);

        if (null === $endpointEngine) {
            return self::databaseResult('inconsistent', null, \sprintf('Database endpoint "%s" is not a recognized MySQL or PostgreSQL endpoint.', $endpoint));
        }

        if ($endpointEngine !== $serviceEngine) {
            return self::databaseResult(
                'inconsistent',
                null,
                \sprintf('Database relationship endpoint "%s" is incompatible with service "%s" of type "%s".', $endpoint, $serviceName, $type),
            );
        }

        return self::databaseResult('valid', $serviceEngine, null);
    }

    /**
     * @param 'valid'|'missing_relationship'|'invalid_relationship'|'missing_service'|'unsupported_service'|'inconsistent' $status
     *
     * @return array{status: 'valid'|'missing_relationship'|'invalid_relationship'|'missing_service'|'unsupported_service'|'inconsistent', engine: ?string, message: ?string}
     */
    private static function databaseResult(
        string $status,
        ?string $engine,
        ?string $message,
    ): array {
        return [
            'status' => $status,
            'engine' => $engine,
            'message' => $message,
        ];
    }

    /**
     * @param list<string> $lines
     */
    private static function findLine(array $lines, int $from, int $to, string $pattern): ?int
    {
        for ($i = $from; $i < $to; ++$i) {
            if (1 === preg_match($pattern, $lines[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The index after the last line nested under the key on line $start.
     *
     * @param list<string> $lines
     */
    private static function blockEnd(array $lines, int $start, int $indent): int
    {
        $count = \count($lines);

        for ($i = $start + 1; $i < $count; ++$i) {
            if (!self::isBlank($lines[$i]) && self::indent($lines[$i]) <= $indent) {
                return $i;
            }
        }

        return $count;
    }

    private static function indent(string $line): int
    {
        return \strlen($line) - \strlen(ltrim($line, " \t"));
    }

    private static function isBlank(string $line): bool
    {
        $line = trim($line);

        return '' === $line || str_starts_with($line, '#');
    }

    private static function engineFromType(string $type): ?string
    {
        if (1 === preg_match('/^(?:mysql|mariadb):/i', $type)) {
            return 'MySQL';
        }

        if (1 === preg_match('/^(?:postgres|postgresql):/i', $type)) {
            return 'PostgreSQL';
        }

        return null;
    }

    private static function engineFromEndpoint(string $endpoint): ?string
    {
        return match (strtolower($endpoint)) {
            'mysql', 'mariadb' => 'MySQL',
            'postgres', 'postgresql' => 'PostgreSQL',
            default => null,
        };
    }
}
