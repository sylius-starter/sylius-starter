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

final readonly class RecaptchaRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'recaptcha';
    }

    public function description(): string
    {
        return 'Remove Google reCAPTCHA v3 to customer registration';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Google reCAPTCHA plugin');

        Composer::allowContribRecipes($app);

        $resourcesDir = \dirname(__DIR__, 2) . '/resources/recaptcha';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        Docker::run($app, 'composer remove karser/karser-recaptcha3-bundle');

        Symfony::cacheClear($app);
    }
}
