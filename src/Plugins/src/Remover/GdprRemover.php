<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Remover;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\RemoverInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class GdprRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'gdpr';
    }

    public function description(): string
    {
        return 'Synolia sylius GDPR plugin';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing GDPR plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove synolia/sylius-gdpr-plugin --no-scripts');

        fs()->remove($app->directory() . '/config/packages/gdpr.yaml');
        fs()->remove($app->directory() . '/config/routes/gdpr.yaml');

        Symfony::cacheClear($app);
    }
}
