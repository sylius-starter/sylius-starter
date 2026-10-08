<?php

declare(strict_types=1);

namespace Castor\Sylius\Tasks;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Sylius\App;
use Castor\Sylius\PaymentGateway\PaymentGateways;

use function Castor\io;

final class PaymentGatewayTasks
{
    public function __construct(
        private readonly string $name,
        private readonly string $directory,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);

        yield [
            'task' => new AsTask('setup', 'sylius:payment-gateways', 'Setup payment gateways', ['setup-payment-gateways']),
            'function' => static function (#[AsRawTokens] array $paymentGateways = [], #[AsOption(description: 'Remove unselected payment gateways')] bool $only = false) use ($app): void {
                // Remove options from the $items
                $paymentGateways = array_filter($paymentGateways, static fn(string $item) => !str_starts_with($item, '--'));

                $availableGateways = PaymentGateways::names();

                $installers = array_map(
                    static fn(callable $installer): callable => static fn() => $installer($app),
                    PaymentGateways::installers(),
                );

                $removers = array_map(
                    static fn(callable $remover): callable => static fn() => $remover($app),
                    PaymentGateways::removers(),
                );

                if ([] === $paymentGateways) {
                    $paymentGateways = io()->choice(
                        'Which payment gateways would you like to use?',
                        $availableGateways,
                        multiSelect: true,
                    );
                }

                if ([] === ($paymentGateways ?? [])) {
                    io()->error('Please select at least one payment gateway');

                    return;
                }

                foreach ($paymentGateways ?? [] as $paymentGateway) {
                    if (!isset($installers[$paymentGateway])) {
                        io()->error(\sprintf('Unknown payment gateway installer "%s", skipping.', $paymentGateway));

                        return;
                    }
                    $installers[$paymentGateway]();
                }

                if ($only) {
                    $paymentGatewaysToRemove = array_diff($availableGateways, $paymentGateways ?? []);

                    foreach ($paymentGatewaysToRemove as $paymentGateway) {
                        if (!isset($removers[$paymentGateway])) {
                            io()->warning(\sprintf('Unknown payment gateway remover "%s", skipping.', $paymentGateway));

                            continue;
                        }
                        $removers[$paymentGateway]();
                    }
                }
            },
        ];
    }
}
