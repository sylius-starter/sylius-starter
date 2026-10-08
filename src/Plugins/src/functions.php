<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins;

use Castor\Attribute\AsListener;
use Castor\Event\AfterBootEvent;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\CallableInstaller;
use SyliusStarter\Core\Component\CallableRemover;
use SyliusStarter\Core\Component\ComponentResolver;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\Plugins\Attribute\AsPluginInstaller;
use SyliusStarter\Plugins\Attribute\AsPluginRemover;
use SyliusStarter\Plugins\Installer\AiDevToolsInstaller;
use SyliusStarter\Plugins\Installer\AltchaInstaller;
use SyliusStarter\Plugins\Installer\BugSnagInstaller;
use SyliusStarter\Plugins\Installer\CmsInstaller;
use SyliusStarter\Plugins\Installer\GdprInstaller;
use SyliusStarter\Plugins\Installer\InvoicingInstaller;
use SyliusStarter\Plugins\Installer\MediaInstaller;
use SyliusStarter\Plugins\Installer\PluginInstallerDescriptor;
use SyliusStarter\Plugins\Installer\ProductBundleInstaller;
use SyliusStarter\Plugins\Installer\RecaptchaInstaller;
use SyliusStarter\Plugins\Installer\RefundInstaller;
use SyliusStarter\Plugins\Installer\WishlistInstaller;
use SyliusStarter\Plugins\Remover\AiDevToolsRemover;
use SyliusStarter\Plugins\Remover\AltchaRemover;
use SyliusStarter\Plugins\Remover\ApiRemover;
use SyliusStarter\Plugins\Remover\BugSnagRemover;
use SyliusStarter\Plugins\Remover\CmsRemover;
use SyliusStarter\Plugins\Remover\GdprRemover;
use SyliusStarter\Plugins\Remover\InvoicingRemover;
use SyliusStarter\Plugins\Remover\PluginRemoverDescriptor;
use SyliusStarter\Plugins\Remover\RecaptchaRemover;
use SyliusStarter\Plugins\Remover\WishlistRemover;
use SyliusStarter\Plugins\Tasks\PluginTasks;

TaskProviderRegistry::register(
    'plugins',
    static fn(SyliusService $service): iterable => (new PluginTasks($service->getName(), $service->getDirectory()))(),
);

#[AsListener(AfterBootEvent::class)]
function initialize(AfterBootEvent $afterBootEvent): void
{
    PluginTasks::addInstaller(new AiDevToolsInstaller());
    PluginTasks::addInstaller(new AltchaInstaller());
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
    PluginTasks::addRemover(new AltchaRemover());
    PluginTasks::addRemover(new ApiRemover());
    PluginTasks::addRemover(new BugSnagRemover());
    PluginTasks::addRemover(new CmsRemover());
    PluginTasks::addRemover(new GdprRemover());
    PluginTasks::addRemover(new InvoicingRemover());
    PluginTasks::addRemover(new RecaptchaRemover());
    PluginTasks::addRemover(new WishlistRemover());

    foreach (ComponentResolver::candidates() as $reflection) {
        $descriptor = resolve_plugin_installer($reflection);

        if (null !== $descriptor) {
            PluginTasks::addInstaller($descriptor->installer instanceof \ReflectionFunction
                ? new CallableInstaller($descriptor->attribute->name, $descriptor->installer->getClosure(), $descriptor->attribute->description)
                : $descriptor->installer);
        }

        $descriptor = resolve_plugin_remover($reflection);

        if (null !== $descriptor) {
            PluginTasks::addRemover($descriptor->remover instanceof \ReflectionFunction
                ? new CallableRemover($descriptor->attribute->name, $descriptor->remover->getClosure(), $descriptor->attribute->description)
                : $descriptor->remover);
        }
    }
}

function resolve_plugin_installer(\ReflectionFunction|\ReflectionClass $reflection): ?PluginInstallerDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsPluginInstaller::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $installer] = $resolved;

    if ($installer instanceof \ReflectionFunction) {
        return new PluginInstallerDescriptor($attribute, $installer);
    }

    return new PluginInstallerDescriptor($attribute, new CallableInstaller($attribute->name, static fn(App $app) => $installer($app), $attribute->description));
}

function resolve_plugin_remover(\ReflectionFunction|\ReflectionClass $reflection): ?PluginRemoverDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsPluginRemover::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $remover] = $resolved;

    if ($remover instanceof \ReflectionFunction) {
        return new PluginRemoverDescriptor($attribute, $remover);
    }

    return new PluginRemoverDescriptor($attribute, new CallableRemover($attribute->name, static fn(App $app) => $remover($app), $attribute->description));
}
