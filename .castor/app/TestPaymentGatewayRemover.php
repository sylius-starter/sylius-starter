<?php

use Castor\Sylius\App;
use Castor\Sylius\Attribute\AsPaymentGatewayRemover;
use function Castor\io;

#[AsPaymentGatewayRemover(name: 'test_payment_gateway_with_class')]
class TestPaymentGatewayRemover
{
    public function __invoke(App $app): void
    {
        io()->success(\sprintf('New payment gateway remover using a custom class is ok (app: %s)', $app->name()));
    }
}
