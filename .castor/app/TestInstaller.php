<?php

use Castor\Sylius\App;
use Castor\Sylius\Attribute\AsPluginInstaller;
use function Castor\io;

#[AsPluginInstaller(name: 'test_installer_with_class')]
class TestInstaller
{
    public function __invoke(App $app): void
    {
        io()->success(\sprintf('New installer using a custom class is ok (app: %s)', $app->name()));
    }
}
