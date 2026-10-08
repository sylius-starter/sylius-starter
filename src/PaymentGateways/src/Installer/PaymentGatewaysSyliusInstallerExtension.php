<?php

declare(strict_types=1);

namespace SyliusStarter\PaymentGateways\Installer;

use Castor\Docker\Installer\Input;
use Castor\Docker\Installer\InputType;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Installer\SyliusInstallerExtensionInterface;
use SyliusStarter\PaymentGateways\PaymentGateways;

use function Castor\run;

/**
 * Asks which payment gateways to configure when a Sylius service is added
 * with "castor docker:service:install sylius", then sets them up once Sylius is ready.
 */
final readonly class PaymentGatewaysSyliusInstallerExtension implements SyliusInstallerExtensionInterface
{
    public function getInputs(): array
    {
        return [
            new Input('payment_gateways', 'Payment gateways', InputType::Choice, array_keys(PaymentGateways::names()), PaymentGateways::names(), true),
        ];
    }

    public function afterScaffold(App $app, array $answers): void
    {
        $paymentGateways = $answers['payment_gateways'] ?? [];

        if ([] === $paymentGateways) {
            return;
        }

        run('castor sylius:payment-gateways:setup --only --no-interaction ' . implode(' ', array_map('escapeshellarg', $paymentGateways)));
    }
}
