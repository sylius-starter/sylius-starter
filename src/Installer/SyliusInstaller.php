<?php

declare(strict_types=1);

namespace Castor\Sylius\Installer;

use Castor\Docker\Installer\AbstractServiceInstaller;
use Castor\Docker\Installer\Ast\Ast;
use Castor\Docker\Installer\Ast\ServiceStatementBuilder;
use Castor\Docker\Installer\Input;
use Castor\Docker\Installer\InputType;
use Castor\Docker\Installer\NeedsDatabase;
use Castor\Docker\Service\DatabaseServiceInterface;
use Castor\Docker\Service\PhpMode;
use Castor\Docker\Service\ServiceInterface;
use Castor\Sylius\App;
use Castor\Sylius\EnvFile;
use Castor\Sylius\PaymentGateway\PaymentGateways;
use Castor\Sylius\Service\SyliusService;
use Castor\Sylius\Util\Assets;
use Castor\Sylius\Util\Database;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Fixtures;
use Castor\Sylius\Util\Symfony;

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
        return [
            new Input('name', 'Application name', InputType::Text, 'app'),
            new Input('directory', 'Directory (relative to castor.php)', InputType::Text, static fn(array $answers): string => (string) ($answers['name'] ?? 'app')),
            new Input('version', 'PHP version', InputType::Text, '8.5'),
            new Input('mode', 'Runtime', InputType::Choice, PhpMode::FrankenPhp->value, [PhpMode::FrankenPhp->value, PhpMode::Fpm->value]),
            new Input('domain', 'Domain', InputType::Text, static fn(array $answers): string => \sprintf('%s.%s', $answers['name'] ?? 'app', context()->data['root_domain'] ?? 'castor.local')),
            new Input('sylius_version', 'Sylius version (empty for latest)', InputType::Text, ''),
            new Input('payment_gateways', 'Payment gateways', InputType::Choice, array_keys(PaymentGateways::names()), PaymentGateways::names(), true),
        ];
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

        if (($answers['domain'] ?? '') !== '') {
            $expression->callMethod('withDomain', [(string) $answers['domain']]);
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

        if (($answers['domain'] ?? '') !== '') {
            $service->withDomain((string) $answers['domain']);
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
        $version = (string) $answers['sylius_version'];
        $directory = (string) $answers['directory'];
        $package = 'sylius/sylius-standard' . ($version !== '' ? ':' . $version : '');
        $paymentGateways = $answers['payment_gateways'] ?? [];

        docker_compose_run(
            \sprintf('composer create-project %s . --no-interaction', $package),
            service: $name . '-builder',
            workDir: '/var/www',
        );

        $app = new App($name, $directory, $domain);

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

        run('castor sylius:payment-gateways:setup --only --no-interaction ' . implode(', ', $paymentGateways));
    }
}
