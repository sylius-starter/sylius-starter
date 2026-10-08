<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\finder;
use function Castor\fs;
use function Castor\io;

final readonly class RecaptchaRemover implements PluginRemoverInterface
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

        $resourcesDir = \dirname(__DIR__, 3) . '/resources/plugin/recaptcha';

        foreach (finder()->files()->in($resourcesDir)->files() as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        Docker::run($app, 'composer remove karser/karser-recaptcha3-bundle');

        Symfony::cacheClear($app);
    }
}
