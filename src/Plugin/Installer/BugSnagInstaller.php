<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\Attribute\AsPluginInstaller;
use Castor\Sylius\App;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class BugSnagInstaller implements PluginInstallerInterface
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
        io()->title('Adding BugSnag Plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require bugsnag/bugsnag-symfony');

        fs()->dumpFile(
            $app->directory() . '/config/packages/bugsnag.yaml',
            <<<'YAML'
                bugsnag:
                    api_key: '%env(BUGSNAG_API_KEY)%'
                    #release_stage: '%env(BUGSNAG_STAGE)%'

                    notify_release_stages:
                        - production
                        #- preproduction

                    discard_classes:
                        - Symfony\Component\HttpKernel\Exception\BadRequestHttpException
                        - Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
                        - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                        - Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
                        - Symfony\Component\Security\Core\Exception\AccessDeniedException
                YAML
        );
        Symfony::cacheClear($app);
    }
}
