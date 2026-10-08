<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways;

use Castor\Attribute\AsListener;
use Castor\Event\AfterBootEvent;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\CallableInstaller;
use SyliusStarter\Core\Component\CallableRemover;
use SyliusStarter\Core\Component\ComponentResolver;
use SyliusStarter\Core\Installer\SyliusInstallerExtensions;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\PaymentGateways\Attribute\AsPaymentGatewayInstaller;
use SyliusStarter\PaymentGateways\Attribute\AsPaymentGatewayRemover;
use SyliusStarter\PaymentGateways\Installer\MollieInstaller;
use SyliusStarter\PaymentGateways\Installer\PaymentGatewayInstallerDescriptor;
use SyliusStarter\PaymentGateways\Installer\PaymentGatewaysSyliusInstallerExtension;
use SyliusStarter\PaymentGateways\Installer\PaypalInstaller;
use SyliusStarter\PaymentGateways\Installer\StripeInstaller;
use SyliusStarter\PaymentGateways\Remover\MollieRemover;
use SyliusStarter\PaymentGateways\Remover\PaymentGatewayRemoverDescriptor;
use SyliusStarter\PaymentGateways\Remover\PaypalRemover;
use SyliusStarter\PaymentGateways\Remover\StripeRemover;
use SyliusStarter\PaymentGateways\Tasks\PaymentGatewayTasks;

TaskProviderRegistry::register(
    'payment_gateways',
    static fn(SyliusService $service): iterable => (new PaymentGatewayTasks($service->getName(), $service->getDirectory()))(),
);

SyliusInstallerExtensions::register('payment_gateways', new PaymentGatewaysSyliusInstallerExtension());

#[AsListener(AfterBootEvent::class)]
function initialize(AfterBootEvent $afterBootEvent): void
{
    PaymentGateways::addInstaller(new MollieInstaller());
    PaymentGateways::addInstaller(new PaypalInstaller());
    PaymentGateways::addInstaller(new StripeInstaller());

    PaymentGateways::addRemover(new MollieRemover());
    PaymentGateways::addRemover(new PaypalRemover());
    PaymentGateways::addRemover(new StripeRemover());

    foreach (ComponentResolver::candidates() as $reflection) {
        $descriptor = resolve_payment_gateway_installer($reflection);

        if (null !== $descriptor) {
            PaymentGateways::addInstaller($descriptor->installer instanceof \ReflectionFunction
                ? new CallableInstaller($descriptor->attribute->name, $descriptor->installer->getClosure())
                : $descriptor->installer);
        }

        $descriptor = resolve_payment_gateway_remover($reflection);

        if (null !== $descriptor) {
            PaymentGateways::addRemover($descriptor->remover instanceof \ReflectionFunction
                ? new CallableRemover($descriptor->attribute->name, $descriptor->remover->getClosure())
                : $descriptor->remover);
        }
    }
}

function resolve_payment_gateway_installer(\ReflectionFunction|\ReflectionClass $reflection): ?PaymentGatewayInstallerDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsPaymentGatewayInstaller::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $installer] = $resolved;

    if ($installer instanceof \ReflectionFunction) {
        return new PaymentGatewayInstallerDescriptor($attribute, $installer);
    }

    return new PaymentGatewayInstallerDescriptor($attribute, new CallableInstaller($attribute->name, static fn(App $app) => $installer($app)));
}

function resolve_payment_gateway_remover(\ReflectionFunction|\ReflectionClass $reflection): ?PaymentGatewayRemoverDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsPaymentGatewayRemover::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $remover] = $resolved;

    if ($remover instanceof \ReflectionFunction) {
        return new PaymentGatewayRemoverDescriptor($attribute, $remover);
    }

    return new PaymentGatewayRemoverDescriptor($attribute, new CallableRemover($attribute->name, static fn(App $app) => $remover($app)));
}
