<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway;

use Castor\Attribute\AsListener;
use Castor\Event\AfterBootEvent;
use Castor\Exception\FunctionConfigurationException;
use Castor\Sylius\App;
use Castor\Sylius\Attribute\AsPaymentGatewayInstaller;
use Castor\Sylius\Attribute\AsPaymentGatewayRemover;
use Castor\Sylius\PaymentGateway\Installer\PaymentGatewayInstallerDescriptor;
use Castor\Sylius\PaymentGateway\Installer\MollieInstaller;
use Castor\Sylius\PaymentGateway\Installer\PaypalInstaller;
use Castor\Sylius\PaymentGateway\Installer\StripeInstaller;
use Castor\Sylius\PaymentGateway\Remover\MollieRemover;
use Castor\Sylius\PaymentGateway\Remover\PaymentGatewayRemoverDescriptor;
use Castor\Sylius\PaymentGateway\Remover\PaypalRemover;
use Castor\Sylius\PaymentGateway\Remover\StripeRemover;
use Castor\Sylius\Plugin\Installer\PluginInstaller;
use Castor\Sylius\Plugin\Remover\PluginRemover;

#[AsListener(AfterBootEvent::class)]
function initialize(AfterBootEvent $afterBootEvent): void
{
    PaymentGateways::addInstaller(new MollieInstaller());
    PaymentGateways::addInstaller(new PaypalInstaller());
    PaymentGateways::addInstaller(new StripeInstaller());

    PaymentGateways::addRemover(new MollieRemover());
    PaymentGateways::addRemover(new PaypalRemover());
    PaymentGateways::addRemover(new StripeRemover());

    $currentFunctions = get_defined_functions()['user'];
    $currentClasses = get_declared_classes();

    foreach ($currentFunctions as $function) {
        $reflectionFunction = new \ReflectionFunction($function);
        $descriptor = resolve_payment_gateway_installer($reflectionFunction);

        if (null !== $descriptor) {
            $installer = new PluginInstaller($descriptor->attribute->name, $descriptor->installer->getClosure());
            PaymentGateways::addInstaller($installer);
        }

        $descriptor = resolve_payment_gateway_remover($reflectionFunction);

        if (null === $descriptor) {
            continue;
        }

        $remover = new PluginRemover($descriptor->attribute->name, $descriptor->remover->getClosure());
        PaymentGateways::addRemover($remover);
    }

    foreach ($currentClasses as $class) {
        $reflectionClass = new \ReflectionClass($class);
        $descriptor = resolve_payment_gateway_installer($reflectionClass);

        if (null !== $descriptor) {
            PaymentGateways::addInstaller($descriptor->installer);
        }

        $descriptor = resolve_payment_gateway_remover($reflectionClass);

        if (null === $descriptor) {
            continue;
        }

        PaymentGateways::addRemover($descriptor->remover);
    }
}

function resolve_payment_gateway_installer(\ReflectionFunction|\ReflectionClass $reflection): ?PaymentGatewayInstallerDescriptor
{
    $attributes = $reflection->getAttributes(AsPaymentGatewayInstaller::class, \ReflectionAttribute::IS_INSTANCEOF);
    if (!\count($attributes)) {
        return null;
    }

    try {
        /** @var AsPaymentGatewayInstaller $installerAttribute */
        $installerAttribute = $attributes[0]->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the attribute "%s".', AsPaymentGatewayInstaller::class), $reflection, $e);
    }

    if ($reflection instanceof \ReflectionFunction) {
        return new PaymentGatewayInstallerDescriptor($installerAttribute, $reflection);
    }

    try {
        $instance = $reflection->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the class "%s".', $reflection->name), $reflection, $e);
    }

    if (!\is_callable($instance)) {
        throw new FunctionConfigurationException(\sprintf('"%s" is not callable.', $reflection->name), $reflection, null);
    }

    return new PaymentGatewayInstallerDescriptor($installerAttribute, new PluginInstaller($installerAttribute->name, static fn(App $app) => $instance($app)));
}

function resolve_payment_gateway_remover(\ReflectionFunction|\ReflectionClass $reflection): ?PaymentGatewayRemoverDescriptor
{
    $attributes = $reflection->getAttributes(AsPaymentGatewayRemover::class, \ReflectionAttribute::IS_INSTANCEOF);
    if (!\count($attributes)) {
        return null;
    }

    try {
        /** @var AsPaymentGatewayRemover $removerAttribute */
        $removerAttribute = $attributes[0]->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the attribute "%s".', AsPaymentGatewayRemover::class), $reflection, $e);
    }

    if ($reflection instanceof \ReflectionFunction) {
        return new PaymentGatewayRemoverDescriptor($removerAttribute, $reflection);
    }

    try {
        $instance = $reflection->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the class "%s".', $reflection->name), $reflection, $e);
    }

    if (!\is_callable($instance)) {
        throw new FunctionConfigurationException(\sprintf('"%s" is not callable.', $reflection->name), $reflection, null);
    }

    return new PaymentGatewayRemoverDescriptor($removerAttribute, new PluginRemover($removerAttribute->name, static fn(App $app) => $instance($app)));
}
