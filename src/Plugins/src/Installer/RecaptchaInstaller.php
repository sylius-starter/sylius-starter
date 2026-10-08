<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Installer;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;
use SyliusStarter\Plugins\PluginResourceCopier;

use function Castor\io;

final readonly class RecaptchaInstaller implements InstallerInterface
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
