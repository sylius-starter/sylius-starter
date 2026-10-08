<?php

declare(strict_types=1);

namespace Castor\Sylius\PaymentGateway\Remover;

use Castor\Sylius\App;
use Castor\Sylius\PhpFile;
use Castor\Sylius\Plugin\Remover\PluginRemoverInterface;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Database;
use Castor\Sylius\Util\Docker;

use function Castor\io;

final readonly class MollieRemover implements PluginRemoverInterface
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
        io()->title('Removing Mollie plugin');

        $this->cleanOrderEntity($app);
        $this->cleanGatewayConfigEntity($app);
        $this->cleanProductEntity($app);
        $this->cleanProductVariantEntity($app);
        $this->cleanAdminUserEntity($app);

        Composer::allowContribRecipes($app);
        Database::rollbackPluginMigrations($app, 'Sylius\MolliePlugin\Migrations');
        Docker::run($app, 'composer remove sylius/mollie-plugin');
    }

    private function cleanOrderEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Order/Order.php'))
            ->removeInterface(\Sylius\MolliePlugin\Entity\OrderInterface::class) // @phpstan-ignore class.notFound
            ->removeTrait(\Sylius\MolliePlugin\Entity\AbandonedEmailOrderTrait::class) // @phpstan-ignore class.notFound
            ->removeTrait(\Sylius\MolliePlugin\Entity\MolliePaymentIdOrderTrait::class) // @phpstan-ignore class.notFound
            ->removeTrait(\Sylius\MolliePlugin\Entity\QRCodeOrderTrait::class) // @phpstan-ignore class.notFound
            ->removeTrait(\Sylius\MolliePlugin\Entity\RecurringOrderTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }

    private function cleanGatewayConfigEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Payment/GatewayConfig.php'))
            ->removeInterface(\Sylius\MolliePlugin\Entity\GatewayConfigInterface::class) // @phpstan-ignore class.notFound
            ->removeConstructor()
            ->removeTrait(\Sylius\MolliePlugin\Entity\GatewayConfigTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }

    private function cleanProductEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Product/Product.php'))
            ->removeInterface(\Sylius\MolliePlugin\Entity\ProductInterface::class) // @phpstan-ignore class.notFound
            ->removeTrait(\Sylius\MolliePlugin\Entity\ProductTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }

    private function cleanProductVariantEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/Product/ProductVariant.php'))
            ->removeInterface(\Sylius\MolliePlugin\Entity\ProductVariantInterface::class) // @phpstan-ignore class.notFound
            ->removeTrait(\Sylius\MolliePlugin\Entity\RecurringProductVariantTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }

    private function cleanAdminUserEntity(App $app): void
    {
        (new PhpFile($app->directory() . '/src/Entity/User/AdminUser.php'))
            ->removeInterface(\Sylius\MolliePlugin\Entity\OnboardingStatusAwareInterface::class) // @phpstan-ignore class.notFound
            ->removeTrait(\Sylius\MolliePlugin\Entity\OnboardingStatusAwareTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;
    }
}
