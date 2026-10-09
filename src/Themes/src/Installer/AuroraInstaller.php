<?php

declare(strict_types=1);

namespace SyliusStarter\Themes\Installer;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Storefront\Hero;
use SyliusStarter\Core\Util\Assets;
use SyliusStarter\Core\Util\Javascript;
use SyliusStarter\Core\Util\Symfony;
use SyliusStarter\Core\Util\Yaml;

use function Castor\finder;
use function Castor\fs;

final class AuroraInstaller implements InstallerInterface
{
    public function name(): string
    {
        return 'aurora';
    }

    public function description(): ?string
    {
        return 'https://github.com/sylius-starter/sylius-starter/blob/main/docs/themes/aurora.md';
    }

    public function __invoke(App $app): void
    {
        Yaml::import($app, 'config/packages/_sylius.yaml', '../sylius/twig_hooks/**/**.php');
        Hero::install($app);

        $resourcesDir = \dirname(__DIR__, 2) . '/resources/aurora';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->copy($resourcesDir . '/' . $file->getRelativePathname(), $app->directory() . '/' . $file->getRelativePathname());
        }

        Javascript::addImport($app, 'assets/shop/entrypoint.js', './styles/app.scss');

        Assets::install($app);
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
