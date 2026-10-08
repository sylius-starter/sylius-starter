<?php

declare(strict_types=1);

namespace Castor\Sylius\Util;

use Castor\Sylius\App;
use Symfony\Component\Process\Process;

use function Castor\Docker\docker_compose_run;

final readonly class Docker
{
    public static function run(App $app, string $command): Process
    {
        return docker_compose_run($command, $app->name() . '-builder');
    }
}
