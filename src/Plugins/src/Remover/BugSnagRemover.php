<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Remover;

use Castor\Sylius\App;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;

use function Castor\io;

final readonly class BugSnagRemover implements PluginRemoverInterface
{
    public function name(): string
    {
        return 'bugsnag';
    }

    public function description(): string
    {
        return 'Official BugSnag notifier for Symfony applications';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing BugSnag plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove bugsnag/bugsnag-symfony');
    }
}
