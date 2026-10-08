<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;

use function Castor\io;

final readonly class Assets
{
    public static function install(App $app): void
    {
        io()->title('Installing the assets');

        Docker::run($app, 'yarn install');
        Docker::run($app, 'bin/console assets:install');
    }

    public static function build(App $app): void
    {
        io()->title('Building the assets');

        Docker::run($app, 'yarn build');
    }
}
