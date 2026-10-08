<?php

use Castor\Sylius\App;
use Castor\Sylius\Attribute\AsPaymentGatewayInstaller;
use function Castor\io;

#[AsPaymentGatewayInstaller(name: 'test_payment_gateway_with_class')]
class TestPaymentGatewayInstaller
{
    public function __invoke(App $app): void
    {
        io()->success(\sprintf('New payment gateway installer using a custom class is ok (app: %s)', $app->name()));
    }
}
