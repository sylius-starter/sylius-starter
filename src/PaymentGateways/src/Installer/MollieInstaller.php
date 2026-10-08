<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway\Installer;

use Castor\Sylius\App;
use Castor\Sylius\PhpFile;
use Castor\Sylius\Plugin\Installer\PluginInstallerInterface;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Database;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Symfony;

use function Castor\io;

final readonly class MollieInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'mollie';
    }

    public function description(): ?string
    {
        return null;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Mollie plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require sylius/mollie-plugin');

        $this->setupOrderEntity($app);
        $this->setupGatewayConfigEntity($app);
        $this->setupProductEntity($app);
        $this->setupProductVariantEntity($app);
        $this->setupAdminUserEntity($app);

        Database::migrate($app);
        Symfony::cacheClear($app);
    }

    private function setupOrderEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Order/Order.php'))
            ->addInterface(\Sylius\MolliePlugin\Entity\OrderInterface::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\MolliePaymentIdOrderTrait::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\QRCodeOrderTrait::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\RecurringOrderTrait::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\AbandonedEmailOrderTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }

    private function setupGatewayConfigEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Payment/GatewayConfig.php'))
            ->addInterface(\Sylius\MolliePlugin\Entity\GatewayConfigInterface::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\GatewayConfigTrait::class) // @phpstan-ignore class.notFound
            ->addConstructor(<<<'PHP'

                    public function __construct()
                    {
                        parent::__construct();

                        $this->initializeMollieGatewayConfig();
                    }

                PHP)
            ->save()
        ;
    }

    private function setupProductEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Product/Product.php'))
            ->addInterface(\Sylius\MolliePlugin\Entity\ProductInterface::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\ProductTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }

    private function setupProductVariantEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Product/ProductVariant.php'))
            ->addInterface(\Sylius\MolliePlugin\Entity\ProductVariantInterface::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\RecurringProductVariantTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }

    private function setupAdminUserEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/User/AdminUser.php'))
            ->addInterface(\Sylius\MolliePlugin\Entity\OnboardingStatusAwareInterface::class) // @phpstan-ignore class.notFound
            ->addTrait(\Sylius\MolliePlugin\Entity\OnboardingStatusAwareTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }
}
