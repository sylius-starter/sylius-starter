<?php

declare(strict_types=1);

namespace Castor\Sylius\Tasks;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Sylius\App;
use Castor\Sylius\PhpFile;

use function Castor\fs;
use function Castor\io;

final class MenuTasks
{
    private const string LISTENER_FILE = 'src/Menu/Admin/RemoveMenuItemsListener.php';

    public function __construct(
        private readonly string $name,
        private readonly string $directory,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);

        yield [
            'task' => new AsTask('remove', 'sylius:menu', 'Remove admin menu items (use --restore / -b to restore previously removed items)'),
            'function' => function (
                #[AsRawTokens]
                array $items = [],
                #[AsOption(description: 'Replace the removed-items list instead of merging', shortcut: 'r')]
                bool $replace = false,
                #[AsOption(description: 'Restore previously removed menu items', shortcut: 'b')]
                bool $restore = false,
            ) use ($app): void {
                // Remove options from the $items
                $items = array_filter($items, static fn(string $item) => !str_starts_with($item, '--'));

                if ($replace && $restore) {
                    io()->error('The --replace and --restore options cannot be used together.');

                    return;
                }

                if ([] === $items) {
                    $items = io()->choice('Choose menu items', self::getMenuItems(), multiSelect: true);
                }

                if ($restore) {
                    $this->restoreMenuItems($app, $items);

                    return;
                }

                if (!fs()->exists($this->buildListenerFilename($app))) {
                    $this->createListener($app, $items);

                    return;
                }

                if ($replace) {
                    $this->createListener($app, $items);

                    return;
                }

                $this->mergeRemovedMenuItems($app, $items);
            },
        ];
    }

    private function mergeRemovedMenuItems(App $app, array $items): void
    {
        $existing = (new PhpFile($this->buildListenerFilename($app)))
            ->findClassConstant('REMOVED_MENU_ITEMS') ?? []
        ;

        $items = array_unique([
            ...$existing,
            ...$items,
        ]);

        $this->createListener($app, $items);
    }

    private function restoreMenuItems(App $app, array $items): void
    {
        $file = $this->buildListenerFilename($app);

        if (!fs()->exists($file)) {
            io()->error(\sprintf('No "%s" found; nothing to restore.', $file));

            return;
        }

        $existing = (new PhpFile($this->buildListenerFilename($app)))
            ->findClassConstant('REMOVED_MENU_ITEMS') ?? []
        ;

        $notFound = array_diff($items, $existing);

        if ([] !== $notFound) {
            io()->warning(\sprintf(
                'The following items were not in the removed list: %s',
                implode(', ', $notFound),
            ));
        }

        $restored = array_intersect($items, $existing);

        if ([] === $restored) {
            io()->comment('No matching items to restore.');

            return;
        }

        $remaining = array_values(array_diff($existing, $items));

        if ([] === $remaining) {
            fs()->remove($file);
            io()->success(\sprintf(
                'Menu items "%s" have been restored; listener removed.',
                implode(', ', $restored),
            ));

            return;
        }

        $this->createListener(
            $app,
            $remaining,
            \sprintf('Menu items "%s" have been restored.', implode(', ', $restored)),
        );
    }

    private function createListener(App $app, array $items, ?string $successMessage = null): void
    {
        $successMessage ??= \sprintf(
            'Menu items "%s" have been removed.',
            implode(', ', $items)
        );

        fs()->dumpFile($this->buildListenerFilename($app), $this->buildListener($items));

        io()->success($successMessage);
    }

    private function buildListenerFilename(App $app): string
    {
        return $app->directory() . '/' . self::LISTENER_FILE;
    }

    /**
     * @param string[] $items
     */
    private function buildListener(array $items): string
    {
        $hiddenItems = \sprintf(
            "[\n%s\n    ]",
            implode(
                ",\n",
                array_map(
                    static fn(string $item): string => \sprintf("        '%s'", $item),
                    $items,
                ),
            ),
        );

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Menu\\Admin;

            use Sylius\\Bundle\\UiBundle\\Menu\\Event\\MenuBuilderEvent;
            use Symfony\\Component\\EventDispatcher\\Attribute\\AsEventListener;

            #[AsEventListener(event: 'sylius.menu.admin.main')]
            final class RemoveMenuItemsListener
            {
                private const array REMOVED_MENU_ITEMS = {$hiddenItems};

                public function __invoke(MenuBuilderEvent \$event): void
                {
                    \$menu = \$event->getMenu();

                    foreach (self::REMOVED_MENU_ITEMS as \$item) {
                        \$parts = explode('/', \$item);

                        if (1 === count(\$parts)) {
                            \$menu->removeChild(\$parts[0]);

                            continue;
                        }

                        \$parent = \$menu->getChild(\$parts[0]);

                        if (null !== \$parent) {
                            \$parent->removeChild(\$parts[1]);
                        }
                    }
                }
            }

            PHP;
    }

    /**
     * @return string[]
     */
    private static function getMenuItems(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'catalog' => 'Catalog',
            'catalog/taxons' => 'Catalog/Taxons',
            'catalog/products' => 'Catalog/Products',
            'catalog/inventory' => 'Catalog/Inventory',
            'catalog/attributes' => 'Catalog/Attributes',
            'catalog/options' => 'Catalog/Options',
            'catalog/association_types' => 'Catalog/Association Types',
            'sales' => 'Sales',
            'sales/orders' => 'Sales/Orders',
            'sales/payments' => 'Sales/Payments',
            'sales/shipments' => 'Sales/Shipments',
            'customers' => 'Customers',
            'customers/customers' => 'Customers/Customers',
            'customers/groups' => 'Customers/Groups',
            'marketing' => 'Marketing',
            'marketing/promotions' => 'Marketing/Promotions',
            'marketing/catalog_promotions' => 'Marketing/Catalog Promotions',
            'marketing/product_reviews' => 'Marketing/Product Reviews',
            'configuration' => 'Configuration',
            'configuration/channels' => 'Configuration/Channels',
            'configuration/countries' => 'Configuration/Countries',
            'configuration/zones' => 'Configuration/Zones',
            'configuration/currencies' => 'Configuration/Currencies',
            'configuration/exchange_rates' => 'Configuration/Exchange Rates',
            'configuration/locales' => 'Configuration/Locales',
            'configuration/payment_methods' => 'Configuration/Payment Methods',
            'configuration/shipping_methods' => 'Configuration/Shipping Methods',
            'configuration/shipping_categories' => 'Configuration/Shipping Categories',
            'configuration/tax_categories' => 'Configuration/Tax Categories',
            'configuration/tax_rates' => 'Configuration/Tax Rates',
            'configuration/admin_users' => 'Configuration/Admin Users',
            'official_support' => 'Official Support',
            'official_support/sylius_plus' => 'Official Support/Sylius Plus',
            'official_support/browse_plugins' => 'Official Support/Browse Plugins',
            'official_support/professional_services' => 'Official Support/Professional Services',
            'official_support/find_a_partner' => 'Official Support/Find a Partner',
            'official_support/sylius_certification' => 'Official Support/Sylius Certification',
            'sylius.ui.administration' => 'Administration',
            'sylius.ui.administration/roles' => 'Administration/Roles',
        ];
    }
}
