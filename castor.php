<?php

declare(strict_types=1);

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;
use Castor\Attribute\AsListener;
use Castor\Docker\Event\RegisterServiceEvent;
use Castor\Docker\Service\PostgresService;
use Castor\Docker\Service\PhpMode;
use Castor\Sylius\Attribute\AsPaymentGatewayInstaller;
use Castor\Sylius\Attribute\AsPaymentGatewayRemover;
use Castor\Sylius\Attribute\AsPluginInstaller;
use Castor\Sylius\Attribute\AsPluginRemover;
use Castor\Sylius\Service\SyliusService;

use function Castor\io;
use function Castor\context;
use function Castor\import;
use function Castor\PHPQa\phpstan;
use function Castor\PHPQa\php_cs_fixer;

import(__DIR__ . '/.castor/app');

#[AsTask(description: 'Fix CS', namespace: 'qa', name: 'cs', aliases: ['cs'])]
function qa_phpcsfixer(bool $dryRun = false): int
{
    $args = null;

    if ($dryRun) {
        $args = ['fix', '--dry-run'];
    }

    return php_cs_fixer(arguments: $args, version: '3.92.4')->getExitCode();
}

#[AsTask(description: 'Run PHPStan', namespace: 'qa', name: 'phpstan', aliases: ['phpstan'])]
function qa_phpstan(bool $generateBaseline = false): int
{
    $args = ['analyze', context()->workingDirectory . '/src'];

    if ($generateBaseline) {
        $args[] = '-b';
    }

    return phpstan(arguments: $args, version: '2.1.32')->getExitCode();
}

#[AsPluginInstaller(name: 'test_installer_with_function')]
function test_installer(): void
{
    io()->success('New installer using a custom function is ok');
}

#[AsPluginRemover(name: 'test_remover_with_function')]
function test_remover(): void
{
    io()->success('New remover using a custom function is ok');
}

#[AsPaymentGatewayInstaller(name: 'test_payment_gateway_with_function')]
function test_payment_gateway_installer(): void
{
    io()->success('New payment gateway installer using a custom function is ok');
}

#[AsPaymentGatewayRemover(name: 'test_payment_gateway_with_function')]
function test_payment_gateway_remover(): void
{
    io()->success('New payment gateway remover using a custom function is ok');
}

#[AsListener(RegisterServiceEvent::class)]
function register_service(RegisterServiceEvent $event): void
{
    $postgres = (new PostgresService())->withVersion('16');
    $event->addService($postgres);
    $event->addService((new SyliusService('app'))->withDirectory(__DIR__ . '/app')->withVersion('8.5')->withMode(PhpMode::FrankenPhp)->withPhpIni(['memory_limit' => '1G'])->withHttpAccess()->withDomain('app.test')->withDatabaseService($postgres));
}
