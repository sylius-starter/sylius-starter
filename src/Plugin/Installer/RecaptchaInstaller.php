<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\App;
use Castor\Sylius\Plugin\PluginResourceCopier;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\io;

final readonly class RecaptchaInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'recaptcha';
    }

    public function description(): string
    {
        return 'Add Google reCAPTCHA v3 to customer registration';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Google reCAPTCHA plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require karser/karser-recaptcha3-bundle');

        PluginResourceCopier::copy($app, 'recaptcha');
        Symfony::cacheClear($app);
    }
}
