<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

final readonly class Upsun
{
    /**
     * @return mixed
     */
    public static function parseConfig(App $app): mixed
    {
        $file = $app->directory() . '/.upsun/config.yaml';
        $contents = file_get_contents($file);

        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Could not read "%s".', $file));
        }

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
