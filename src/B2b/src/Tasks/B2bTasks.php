<?php

declare(strict_types=1);

namespace SyliusStarter\B2b\Tasks;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use SyliusStarter\B2b\B2bFeatures;
use SyliusStarter\Core\App;
use SyliusStarter\Core\Util\Symfony;
use SyliusStarter\Core\Util\Yaml;

use function Castor\io;

final class B2bTasks
{
    public function __construct(
        private readonly string $name,
        private readonly string $directory,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);

        yield [
            'task' => new AsTask('enable', 'sylius:b2b', 'Enable b2b features'),
            'function' => static function (#[AsRawTokens] array $features = []) use ($app): void {
                $features = array_values(array_filter(
                    $features,
                    static fn(string $feature): bool => !str_starts_with($feature, '-'),
                ));

                $featureHandlers = B2bFeatures::features();
                $availableFeatures = B2bFeatures::choices();

                if ([] === $features) {
                    $features = io()->choice(
                        'Which B2B features would you like to enable?',
                        $availableFeatures,
                        multiSelect: true,
                    );
                }

                Yaml::import($app, 'config/packages/_sylius.yaml', '../sylius/twig_hooks/**/**.php');

                foreach ($features ?? [] as $feature) {
                    if (!isset($featureHandlers[$feature])) {
                        io()->warning(\sprintf('Unknown B2B feature "%s", skipping.', $feature));

                        continue;
                    }

                    $featureHandlers[$feature]($app);
                }

                Symfony::cacheClear($app);
            },
        ];
    }
}
