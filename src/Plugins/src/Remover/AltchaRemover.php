<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;

use function Castor\finder;
use function Castor\fs;
use function Castor\io;

final readonly class AltchaRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'altcha';
    }

    public function description(): string
    {
        return 'Remove ALTCHA from customer registration';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing ALTCHA plugin');

        Composer::allowContribRecipes($app);

        $resourcesDir = \dirname(__DIR__, 2) . '/resources/altcha';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        // The recipe unconfigure step removes the bundle, the route and the env var.
        Docker::run($app, 'composer remove tito10047/altcha-bundle');
        fs()->remove($app->directory() . '/config/packages/altcha.yaml');

        Symfony::cacheClear($app);
    }
}
