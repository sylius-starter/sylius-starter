<?php

declare(strict_types=1);

namespace Castor\Sylius\Theme;

use Castor\Attribute\AsListener;
use Castor\Event\AfterBootEvent;
use Castor\Exception\FunctionConfigurationException;
use Castor\Sylius\App;
use Castor\Sylius\Attribute\AsThemeInstaller;
use Castor\Sylius\Attribute\AsThemeRemover;
use Castor\Sylius\Plugin\Installer\PluginInstaller;
use Castor\Sylius\Plugin\Remover\PluginRemover;
use Castor\Sylius\Theme\Installer\CanvasInstaller;
use Castor\Sylius\Theme\Installer\PromptDarkThemeInstaller;
use Castor\Sylius\Theme\Installer\PromptLightThemeInstaller;
use Castor\Sylius\Theme\Installer\BlushInstaller;
use Castor\Sylius\Theme\Installer\ThemeInstallerDescriptor;
use Castor\Sylius\Theme\Installer\VoltInstaller;
use Castor\Sylius\Theme\Remover\CanvasRemover;
use Castor\Sylius\Theme\Remover\PromptDarkThemeRemover;
use Castor\Sylius\Theme\Remover\PromptLightThemeRemover;
use Castor\Sylius\Theme\Remover\BlushRemover;
use Castor\Sylius\Theme\Remover\ThemeRemoverDescriptor;
use Castor\Sylius\Theme\Remover\VoltRemover;

#[AsListener(AfterBootEvent::class)]
function initialize(AfterBootEvent $afterBootEvent): void
{
    Themes::addInstaller(new CanvasInstaller());
    Themes::addInstaller(new PromptDarkThemeInstaller());
    Themes::addInstaller(new PromptLightThemeInstaller());
    Themes::addInstaller(new BlushInstaller());
    Themes::addInstaller(new VoltInstaller());

    Themes::addRemover(new CanvasRemover());
    Themes::addRemover(new PromptDarkThemeRemover());
    Themes::addRemover(new PromptLightThemeRemover());
    Themes::addRemover(new BlushRemover());
    Themes::addRemover(new VoltRemover());

    $currentFunctions = get_defined_functions()['user'];
    $currentClasses = get_declared_classes();

    foreach ($currentFunctions as $function) {
        $reflectionFunction = new \ReflectionFunction($function);
        $descriptor = resolve_theme_installer($reflectionFunction);

        if (null !== $descriptor) {
            $installer = new PluginInstaller($descriptor->attribute->name, $descriptor->installer->getClosure());
            Themes::addInstaller($installer);
        }

        $descriptor = resolve_theme_remover($reflectionFunction);

        if (null === $descriptor) {
            continue;
        }

        $remover = new PluginRemover($descriptor->attribute->name, $descriptor->remover->getClosure());
        Themes::addRemover($remover);
    }

    foreach ($currentClasses as $class) {
        $reflectionClass = new \ReflectionClass($class);
        $descriptor = resolve_theme_installer($reflectionClass);

        if (null !== $descriptor) {
            Themes::addInstaller($descriptor->installer);
        }

        $descriptor = resolve_theme_remover($reflectionClass);

        if (null === $descriptor) {
            continue;
        }

        Themes::addRemover($descriptor->remover);
    }
}

function resolve_theme_installer(\ReflectionFunction|\ReflectionClass $reflection): ?ThemeInstallerDescriptor
{
    $attributes = $reflection->getAttributes(AsThemeInstaller::class, \ReflectionAttribute::IS_INSTANCEOF);
    if (!\count($attributes)) {
        return null;
    }

    try {
        /** @var AsThemeInstaller $installerAttribute */
        $installerAttribute = $attributes[0]->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the attribute "%s".', AsThemeInstaller::class), $reflection, $e);
    }

    if ($reflection instanceof \ReflectionFunction) {
        return new ThemeInstallerDescriptor($installerAttribute, $reflection);
    }

    try {
        $instance = $reflection->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the class "%s".', $reflection->name), $reflection, $e);
    }

    if (!\is_callable($instance)) {
        throw new FunctionConfigurationException(\sprintf('"%s" is not callable.', $reflection->name), $reflection, null);
    }

    return new ThemeInstallerDescriptor($installerAttribute, new PluginInstaller($installerAttribute->name, static fn(App $app) => $instance($app)));
}

function resolve_theme_remover(\ReflectionFunction|\ReflectionClass $reflection): ?ThemeRemoverDescriptor
{
    $attributes = $reflection->getAttributes(AsThemeRemover::class, \ReflectionAttribute::IS_INSTANCEOF);
    if (!\count($attributes)) {
        return null;
    }

    try {
        /** @var AsThemeRemover $removerAttribute */
        $removerAttribute = $attributes[0]->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the attribute "%s".', AsThemeRemover::class), $reflection, $e);
    }

    if ($reflection instanceof \ReflectionFunction) {
        return new ThemeRemoverDescriptor($removerAttribute, $reflection);
    }

    try {
        $instance = $reflection->newInstance();
    } catch (\Throwable $e) {
        throw new FunctionConfigurationException(\sprintf('Could not instantiate the class "%s".', $reflection->name), $reflection, $e);
    }

    if (!\is_callable($instance)) {
        throw new FunctionConfigurationException(\sprintf('"%s" is not callable.', $reflection->name), $reflection, null);
    }

    return new ThemeRemoverDescriptor($removerAttribute, new PluginRemover($removerAttribute->name, static fn(App $app) => $instance($app)));
}
