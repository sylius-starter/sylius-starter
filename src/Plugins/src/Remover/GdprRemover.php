<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Remover;

use Castor\Sylius\App;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class GdprRemover implements PluginRemoverInterface
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
