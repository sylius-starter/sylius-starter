<?php

declare(strict_types=1);

namespace SyliusStarter\Core\Util;

use SyliusStarter\Core\App;

use function Castor\io;

final readonly class Assets
{
    public static function install(App $app): void
    {
        io()->title('Installing the assets');

        Docker::run($app, 'yarn install');
        Docker::run($app, 'bin/console assets:install');
    }

    public static function build(App $app, bool $production = true): void
    {
        io()->title('Building the assets');

        Docker::run($app, 'yarn install');
        Docker::run($app, $production ? 'yarn build:prod' : 'yarn build');
        Docker::run($app, 'bin/console assets:install');
    }
}
