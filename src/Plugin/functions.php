<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin;

use Castor\Attribute\AsListener;
use Castor\Docker\Event\RegisterServiceInstallerEvent;
use Castor\Event\AfterBootEvent;
use Castor\Exception\FunctionConfigurationException;
use Castor\Sylius\App;
use Castor\Sylius\Attribute\AsPluginInstaller;
use Castor\Sylius\Attribute\AsPluginRemover;
use Castor\Sylius\Installer\SyliusInstaller;
use Castor\Sylius\Plugin\Installer\AiDevToolsInstaller;
use Castor\Sylius\Plugin\Installer\BugSnagInstaller;
use Castor\Sylius\Plugin\Installer\CmsInstaller;
use Castor\Sylius\Plugin\Installer\GdprInstaller;
use Castor\Sylius\Plugin\Installer\InvoicingInstaller;
use Castor\Sylius\Plugin\Installer\MediaInstaller;
use Castor\Sylius\Plugin\Installer\PluginInstaller;
use Castor\Sylius\Plugin\Installer\PluginInstallerDescriptor;
use Castor\Sylius\Plugin\Installer\ProductBundleInstaller;
use Castor\Sylius\Plugin\Installer\RecaptchaInstaller;
use Castor\Sylius\Plugin\Installer\RecaptchaRemover;
use Castor\Sylius\Plugin\Installer\RefundInstaller;
use Castor\Sylius\Plugin\Installer\WishlistInstaller;
use Castor\Sylius\Plugin\Remover\AiDevToolsRemover;
use Castor\Sylius\Plugin\Remover\ApiRemover;
use Castor\Sylius\Plugin\Remover\BugSnagRemover;
use Castor\Sylius\Plugin\Remover\CmsRemover;
use Castor\Sylius\Plugin\Remover\GdprRemover;
use Castor\Sylius\Plugin\Remover\InvoicingRemover;
use Castor\Sylius\Plugin\Remover\PluginRemover;
use Castor\Sylius\Plugin\Remover\PluginRemoverDescriptor;
use Castor\Sylius\Plugin\Remover\WishlistRemover;
use Castor\Sylius\Tasks\PluginTasks;

#[AsListener(RegisterServiceInstallerEvent::class)]
function register_builtin_installers(RegisterServiceInstallerEvent $event): void
{
    $event->addInstaller(new SyliusInstaller());
}

#[AsListener(AfterBootEvent::class)]
function initialize(AfterBootEvent $afterBootEvent): void
{
    PluginTasks::addInstaller(new AiDevToolsInstaller());
    PluginTasks::addInstaller(new BugSnagInstaller());
    PluginTasks::addInstaller(new CmsInstaller());
    PluginTasks::addInstaller(new GdprInstaller());
    PluginTasks::addInstaller(new InvoicingInstaller());
    PluginTasks::addInstaller(new MediaInstaller());
    PluginTasks::addInstaller(new ProductBundleInstaller());
    PluginTasks::addInstaller(new RecaptchaInstaller());
    PluginTasks::addInstaller(new RefundInstaller());
    PluginTasks::addInstaller(new WishlistInstaller());

    PluginTasks::addRemover(new AiDevToolsRemover());
    PluginTasks::addRemover(new ApiRemover());
    PluginTasks::addRemover(new BugSnagRemover());
    PluginTasks::addRemover(new CmsRemover());
    PluginTasks::addRemover(new GdprRemover());
    PluginTasks::addRemover(new InvoicingRemover());
    PluginTasks::addRemover(new RecaptchaRemover());
    PluginTasks::addRemover(new WishlistRemover());

    $currentFunctions = get_defined_functions()['user'];
    $currentClasses = get_declared_classes();

    foreach ($currentFunctions as $function) {
        $reflectionFunction = new \ReflectionFunction($function);
        $descriptor = resolve_plugin_installer($reflectionFunction);

        if (null !== $descriptor) {
            $installer = new PluginInstaller($descriptor->attribute->name, $descriptor->installer->getClosure(), $descriptor->attribute->description);
            PluginTasks::addInstaller($installer);
        }

        $descriptor = resolve_plugin_remover($reflectionFunction);

        if (null === $descriptor) {
            continue;
        }

        $remover = new PluginRemover($descriptor->attribute->name, $descriptor->remover->getClosure(), $descriptor->attribute->description);
        PluginTasks::addRemover($remover);
    }

    foreach ($currentClasses as $class) {
        $reflectionClass = new \ReflectionClass($class);
        $descriptor = resolve_plugin_installer($reflectionClass);

        if (null !== $descriptor) {
            PluginTasks::addInstaller($descriptor->installer);
        }

        $descriptor = resolve_plugin_remover($reflectionClass);

        if (null === $descriptor) {
            continue;
        }

        PluginTasks::addRemover($descriptor->remover);
    }
}

function resolve_plugin_installer(\ReflectionFunction|\ReflectionClass $reflection): ?PluginInstallerDescriptor
{
    $attributes = $reflection->getAttributes(AsPluginInstaller::class, \ReflectionAttribute::IS_INSTANCEOF);
    if (!\count($attributes)) {
        return null;
    }

    try {
        /** @var AsPluginInstaller $installerAttribute */
        $installerAttribute = $attributes[0]->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the attribute "%s".', AsPluginInstaller::class), $reflection, $e);
    }

    if ($reflection instanceof \ReflectionFunction) {
        return new PluginInstallerDescriptor($installerAttribute, $reflection);
    }

    try {
        $instance = $reflection->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the class "%s".', $reflection->name), $reflection, $e);
    }

    if (!\is_callable($instance)) {
        throw new FunctionConfigurationException(\sprintf('"%s" is not callable.', $reflection->name), $reflection, null);
    }

    return new PluginInstallerDescriptor($installerAttribute, new PluginInstaller($installerAttribute->name, static fn(App $app) => $instance($app), $installerAttribute->description));
}

function resolve_plugin_remover(\ReflectionFunction|\ReflectionClass $reflection): ?PluginRemoverDescriptor
{
    $attributes = $reflection->getAttributes(AsPluginRemover::class, \ReflectionAttribute::IS_INSTANCEOF);
    if (!\count($attributes)) {
        return null;
    }

    try {
        /** @var AsPluginRemover $removerAttribute */
        $removerAttribute = $attributes[0]->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the attribute "%s".', AsPluginRemover::class), $reflection, $e);
    }

    if ($reflection instanceof \ReflectionFunction) {
        return new PluginRemoverDescriptor($removerAttribute, $reflection);
    }

    try {
        $instance = $reflection->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the class "%s".', $reflection->name), $reflection, $e);
    }

    if (!\is_callable($instance)) {
        throw new FunctionConfigurationException(\sprintf('"%s" is not callable.', $reflection->name), $reflection, null);
    }

    return new PluginRemoverDescriptor($removerAttribute, new PluginRemover($removerAttribute->name, static fn(App $app) => $instance($app), $removerAttribute->description));
}
