<?php

declare(strict_types=1);

namespace Castor\Sylius\Theme\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;
use Castor\Sylius\Util\Assets;
use Castor\Sylius\Util\Javascript;
use Castor\Sylius\Util\Symfony;
use Castor\Sylius\Util\Yaml;

use function Castor\finder;
use function Castor\fs;

final class VoltInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'volt';
    }

    public function description(): ?string
    {
        return 'https://github.com/castor-php/sylius/blob/main/docs/themes/volt.md';
    }

    public function __invoke(App $app): void
    {
        Yaml::import($app, 'config/packages/_sylius.yaml', '../sylius/twig_hooks/**/**.php');

        $resourcesDir = \dirname(__DIR__, 3) . '/resources/theme/volt';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->copy($resourcesDir . '/' . $file->getRelativePathname(), $app->directory() . '/' . $file->getRelativePathname());
        }

        Javascript::addImport($app, 'assets/shop/entrypoint.js', './styles/app.scss');

        Assets::install($app);
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
