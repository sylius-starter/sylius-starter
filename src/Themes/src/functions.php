<?php

declare(strict_types=1);

namespace SyliusStarter\Themes;

use Castor\Attribute\AsListener;
use Castor\Event\AfterBootEvent;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\CallableInstaller;
use SyliusStarter\Core\Component\CallableRemover;
use SyliusStarter\Core\Component\ComponentResolver;
use SyliusStarter\Core\Installer\SyliusInstallerExtensions;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\Themes\Attribute\AsThemeInstaller;
use SyliusStarter\Themes\Attribute\AsThemeRemover;
use SyliusStarter\Themes\Installer\BlushInstaller;
use SyliusStarter\Themes\Installer\CanvasInstaller;
use SyliusStarter\Themes\Installer\LagoonInstaller;
use SyliusStarter\Themes\Installer\PromptDarkThemeInstaller;
use SyliusStarter\Themes\Installer\PromptLightThemeInstaller;
use SyliusStarter\Themes\Installer\ThemeInstallerDescriptor;
use SyliusStarter\Themes\Installer\ThemesSyliusInstallerExtension;
use SyliusStarter\Themes\Installer\VoltInstaller;
use SyliusStarter\Themes\Remover\BlushRemover;
use SyliusStarter\Themes\Remover\CanvasRemover;
use SyliusStarter\Themes\Remover\LagoonRemover;
use SyliusStarter\Themes\Remover\PromptDarkThemeRemover;
use SyliusStarter\Themes\Remover\PromptLightThemeRemover;
use SyliusStarter\Themes\Remover\ThemeRemoverDescriptor;
use SyliusStarter\Themes\Remover\VoltRemover;
use SyliusStarter\Themes\Tasks\ThemeTasks;

TaskProviderRegistry::register(
    'themes',
    static fn(SyliusService $service): iterable => (new ThemeTasks($service->getName(), $service->getDirectory()))(),
);

SyliusInstallerExtensions::register('themes', new ThemesSyliusInstallerExtension());

#[AsListener(AfterBootEvent::class)]
function initialize(AfterBootEvent $afterBootEvent): void
{
    Themes::addInstaller(new CanvasInstaller());
    Themes::addInstaller(new PromptDarkThemeInstaller());
    Themes::addInstaller(new PromptLightThemeInstaller());
    Themes::addInstaller(new BlushInstaller());
    Themes::addInstaller(new VoltInstaller());
    Themes::addInstaller(new LagoonInstaller());

    Themes::addRemover(new CanvasRemover());
    Themes::addRemover(new PromptDarkThemeRemover());
    Themes::addRemover(new PromptLightThemeRemover());
    Themes::addRemover(new BlushRemover());
    Themes::addRemover(new VoltRemover());
    Themes::addRemover(new LagoonRemover());

    foreach (ComponentResolver::candidates() as $reflection) {
        $descriptor = resolve_theme_installer($reflection);

        if (null !== $descriptor) {
            Themes::addInstaller($descriptor->installer instanceof \ReflectionFunction
                ? new CallableInstaller($descriptor->attribute->name, $descriptor->installer->getClosure())
                : $descriptor->installer);
        }

        $descriptor = resolve_theme_remover($reflection);

        if (null !== $descriptor) {
            Themes::addRemover($descriptor->remover instanceof \ReflectionFunction
                ? new CallableRemover($descriptor->attribute->name, $descriptor->remover->getClosure())
                : $descriptor->remover);
        }
    }
}

function resolve_theme_installer(\ReflectionFunction|\ReflectionClass $reflection): ?ThemeInstallerDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsThemeInstaller::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $installer] = $resolved;

    if ($installer instanceof \ReflectionFunction) {
        return new ThemeInstallerDescriptor($attribute, $installer);
    }

    return new ThemeInstallerDescriptor($attribute, new CallableInstaller($attribute->name, static fn(App $app) => $installer($app)));
}

function resolve_theme_remover(\ReflectionFunction|\ReflectionClass $reflection): ?ThemeRemoverDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsThemeRemover::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $remover] = $resolved;

    if ($remover instanceof \ReflectionFunction) {
        return new ThemeRemoverDescriptor($attribute, $remover);
    }

    return new ThemeRemoverDescriptor($attribute, new CallableRemover($attribute->name, static fn(App $app) => $remover($app)));
}
