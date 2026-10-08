<?php

declare(strict_types=1);

namespace SyliusStarter\Plugins\Installer;

use SyliusStarter\Core\App;
use SyliusStarter\Core\Component\InstallerInterface;
use SyliusStarter\Core\Util\Composer;
use SyliusStarter\Core\Util\Docker;
use SyliusStarter\Core\Util\Symfony;
use SyliusStarter\Core\Util\Yaml;
use SyliusStarter\Plugins\PluginResourceCopier;

use function Castor\fs;
use function Castor\io;

final readonly class AltchaInstaller implements InstallerInterface
{
    public function name(): string
    {
        return 'altcha';
    }

    public function description(): string
    {
        return 'Add ALTCHA (self-hosted, privacy-friendly captcha) to customer registration';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding ALTCHA plugin');

        // The contrib recipe registers the bundle, the challenge route and the ALTCHA_SECRET env var.
        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require tito10047/altcha-bundle');

        // Override the recipe config: Sylius ships StimulusBundle with Webpack Encore, but the bundle
        // Stimulus controller is not registered in assets/controllers.json, so we load the widget script directly.
        fs()->dumpFile(
            $app->directory() . '/config/packages/altcha.yaml',
            <<<'YAML'
                altcha:
                    enable: true
                    hmacSignature: '%env(ALTCHA_SECRET)%'
                    floating: false
                    use_stimulus: false
                    include_script: true

                when@test:
                    altcha:
                        enable: false

                YAML
        );

        Yaml::import($app, 'config/packages/_sylius.yaml', '../sylius/twig_hooks/**/**.php');

        PluginResourceCopier::copy($app, 'altcha');
        Symfony::cacheClear($app);
    }
}
