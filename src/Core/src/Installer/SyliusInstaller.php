<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Installer;

use Castor\Docker\Installer\AbstractServiceInstaller;
use Castor\Docker\Installer\Ast\Ast;
use Castor\Docker\Installer\Ast\ServiceStatementBuilder;
use Castor\Docker\Installer\Input;
use Castor\Docker\Installer\InputType;
use Castor\Docker\Installer\NeedsDatabase;
use Castor\Docker\Service\DatabaseServiceInterface;
use Castor\Docker\Service\PhpMode;
use Castor\Docker\Service\ServiceInterface;
use SyliusStarter\Core\App;
use SyliusStarter\Core\EnvFile;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Util\Assets;
use SyliusStarter\Core\Util\Database;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Fixtures;
use SyliusStarter\Core\Util\Symfony;

use function Castor\context;
use function Castor\Docker\docker_compose_run;
use function Castor\run;

final class SyliusInstaller extends AbstractServiceInstaller implements NeedsDatabase
{
    public function getName(): string
    {
        return 'sylius';
    }

    public function getDescription(): string
    {
        return 'Sylius application';
    }

    public function getInputs(): array
    {
        $inputs = [
            new Input('name', 'Application name', InputType::Text, 'app'),
            new Input('directory', 'Directory (relative to castor.php)', InputType::Text, static fn(array $answers): string => (string) ($answers['name'] ?? 'app')),
            new Input('version', 'PHP version', InputType::Text, '8.5'),
            new Input('mode', 'Runtime', InputType::Choice, PhpMode::FrankenPhp->value, [PhpMode::FrankenPhp->value, PhpMode::Fpm->value]),
            new Input('domain', 'Domain', InputType::Text, static fn(array $answers): string => \sprintf('%s.%s', $answers['name'] ?? 'app', context()->data['root_domain'] ?? 'castor.local')),
            new Input('subdomain', 'Subdomain (empty to use the domain as hostname)', InputType::Text, ''),
            new Input('sylius_version', 'Sylius version (empty for latest)', InputType::Text, ''),
        ];

        foreach (SyliusInstallerExtensions::all() as $extension) {
            array_push($inputs, ...$extension->getInputs());
        }

        return $inputs;
    }

    public function buildStatements(ServiceStatementBuilder $builder, array $answers): void
    {
        $expression = $builder->addNewServiceAst(SyliusService::class, [(string) $answers['name']])
            ->callMethod('withDirectory', [Ast::raw(\sprintf("__DIR__ . '/%s'", $answers['directory']))])
            ->callMethod('withVersion', [(string) $answers['version']])
            ->callMethod('withMode', [Ast::raw('PhpMode::' . PhpMode::from((string) $answers['mode'])->name)])
            ->callMethod('withPhpIni', [['memory_limit' => '1G']])
            ->callMethod('withHttpAccess')
        ;
        $builder->addImport(PhpMode::class);

        $routedDomains = self::routedDomains($answers);
        if ([] !== $routedDomains) {
            $expression->callMethod('withDomain', $routedDomains);
        }

        if (($answers['database'] ?? null) !== null) {
            $expression->callMethod('withDatabaseService', [Ast::var((string) $answers['database'])]);
        }
    }

    public function createInstance(array $answers): ServiceInterface
    {
        $service = (new SyliusService((string) $answers['name']))
            ->withDirectory(context()->workingDirectory . '/' . $answers['directory'])
        ;

        $routedDomains = self::routedDomains($answers);
        if ([] !== $routedDomains) {
            $service->withDomain(...$routedDomains);
        }

        if (($answers['database_instance'] ?? null) instanceof DatabaseServiceInterface) {
            $service->withDatabaseService($answers['database_instance']);
        }

        return $service;
    }

    public function scaffold(array $answers): void
    {
        $name = (string) $answers['name'];
        $domain = (string) $answers['domain'];
        $subdomain = self::normalizedSubdomain($answers);
        $version = (string) $answers['sylius_version'];
        $directory = (string) $answers['directory'];
        $package = 'sylius/sylius-standard' . ($version !== '' ? ':' . $version : '');

        docker_compose_run(
            \sprintf('composer create-project %s . --no-interaction', $package),
            service: $name . '-builder',
            workDir: '/var/www',
        );

        $app = new App(
            $name,
            $directory,
            '' !== $domain ? $domain : null,
            $subdomain,
        );

        $envFile = \sprintf('%s/.env', $directory);

        (new EnvFile($envFile))
            ->set('SYMFONY_TRUSTED_PROXIES', 'PRIVATE_SUBNETS')
            ->set('SYMFONY_TRUSTED_HEADERS', 'forwarded,x-forwarded-for,x-forwarded-host,x-forwarded-proto,x-forwarded-port')
            ->save()
        ;

        run('castor up');

        Fixtures::createSuite($app);
        Fixtures::createDefaultChannel($app);

        // New fixtures files need to be detected
        Symfony::cacheClear($app);

        Docker::run($app, 'yarn install');
        Assets::build($app);
        Database::migrate($app);
        Fixtures::load($app);

        foreach (SyliusInstallerExtensions::all() as $extension) {
            $extension->afterScaffold($app, $answers);
        }
    }

    /**
     * Domains registered on the Castor service: apex first (used by import as App domain),
     * then the shop hostname when a subdomain is configured.
     *
     * @param array<string, mixed> $answers
     *
     * @return list<string>
     */
    private static function routedDomains(array $answers): array
    {
        $domain = trim((string) ($answers['domain'] ?? ''));
        if ('' === $domain) {
            return [];
        }

        $domains = [$domain];
        $subdomain = self::normalizedSubdomain($answers);
        if (null !== $subdomain) {
            $hostname = $subdomain . '.' . $domain;
            if ($hostname !== $domain) {
                $domains[] = $hostname;
            }
        }

        return $domains;
    }

    /**
     * @param array<string, mixed> $answers
     */
    private static function normalizedSubdomain(array $answers): ?string
    {
        $subdomain = trim((string) ($answers['subdomain'] ?? ''));

        return '' === $subdomain ? null : $subdomain;
    }
}
