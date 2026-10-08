<?php

declare(strict_types=1);

namespace Castor\Sylius\Plugin\Installer;

use Castor\Sylius\App;
use Castor\Sylius\PhpFile;
use Castor\Sylius\Util\Assets;
use Castor\Sylius\Util\Composer;
use Castor\Sylius\Util\Database;
use Castor\Sylius\Util\Docker;
use Castor\Sylius\Util\Filesystem;
use Castor\Sylius\Util\Symfony;
use Castor\Sylius\Util\Yaml;

use function Castor\fs;
use function Castor\io;

final readonly class MediaInstaller implements PluginInstallerInterface
{
    public function name(): string
    {
        return 'media';
    }

    public function description(): string
    {
        return 'Media management bundle for Symfony applications';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Media plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require jolicode/media-bundle');
        Symfony::addBundle($app, 'JoliCode\MediaBundle\Bridge\Sylius\JoliMediaSyliusBundle');

        Yaml::uncommentBlock(
            $app,
            'config/packages/joli_media.yaml',
            <<<'YAML'
                imports:
                    - { resource: '@JoliMediaSyliusBundle/config/app.php' }
                YAML,
        );

        Yaml::uncommentBlock(
            $app,
            'config/packages/joli_media.yaml',
            <<<'YAML'
                libraries:
                    default:
                        original:
                            flysystem: "filesystem.original.storage"
                            url_generator:
                                strategy: folder
                                path: /media/original/
                        cache:
                            flysystem: "filesystem.cache.storage"
                            must_store_when_generating_url: false
                            url_generator:
                                strategy: folder
                                path: /media/cache/
                YAML,
        );

        Yaml::uncommentBlock(
            $app,
            'config/routes/joli_media.yaml',
            <<<'YAML'
                _joli_media_sylius_admin:
                    resource: "@JoliMediaSyliusBundle/src/Admin/Controller/"
                    prefix: /admin/media
                YAML,
        );

        Yaml::appendToSection(
            $app,
            'config/services.yaml',
            'services',
            <<<'YAML'
                    # Filesystem Adapters for the Jolicode Media Bundle
                    filesystem.original.adapter:
                        class: League\Flysystem\Local\LocalFilesystemAdapter
                        arguments:
                            '$location': '%kernel.project_dir%/public/media/original'

                    filesystem.original.storage:
                        class: League\Flysystem\Filesystem
                        arguments:
                            '$adapter': '@filesystem.original.adapter'

                    filesystem.cache.adapter:
                        class: League\Flysystem\Local\LocalFilesystemAdapter
                        arguments:
                            '$location': '%kernel.project_dir%/public/media/cache'

                    filesystem.cache.storage:
                        class: League\Flysystem\Filesystem
                        arguments:
                            '$adapter': '@filesystem.cache.adapter'

                    # Form extensions to use the Jolicode media choice
                    JoliCode\MediaBundle\Bridge\Sylius\Admin\Form\Extension\AvatarImageTypeExtension: null
                    JoliCode\MediaBundle\Bridge\Sylius\Admin\Form\Extension\ProductImageTypeExtension: null
                    JoliCode\MediaBundle\Bridge\Sylius\Admin\Form\Extension\TaxonImageTypeExtension: null
                YAML,
        );

        $this->createMediaLibraryMenu($app);

        $baseDir = $app->directory();

        (new PhpFile($baseDir . '/src/Entity/Product/ProductImage.php'))
            ->addTrait(\JoliCode\MediaBundle\Bridge\Sylius\Doctrine\ORM\EntityWithMediaImageTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;

        (new PhpFile($baseDir . '/src/Entity/Product/TaxonImage.php'))
            ->addTrait(\JoliCode\MediaBundle\Bridge\Sylius\Doctrine\ORM\EntityWithMediaImageTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;

        (new PhpFile($baseDir . '/src/Entity/Product/AvatarImage.php'))
            ->addTrait(\JoliCode\MediaBundle\Bridge\Sylius\Doctrine\ORM\EntityWithMediaImageTrait::class) // @phpstan-ignore class.notFound
            ->save()
        ;

        Symfony::cacheClear($app);

        // Ensure all current migrations have been executed.
        Database::migrate($app);

        Database::diff($app);

        $latestMigration = Filesystem::latestFile($app, 'migrations');

        if (null === $latestMigration) {
            io()->error('No lastest migration found.');
        }

        (new PhpFile($baseDir . '/migrations/' . $latestMigration))
            ->appendToMethod(
                'up',
                <<<'PHP'
                    // These lines synchronize the media with the current path.
                    $this->addSql('UPDATE sylius_avatar_image SET media = path');
                    $this->addSql('UPDATE sylius_product_image SET media = path');
                    $this->addSql('UPDATE sylius_taxon_image SET media = path');
                    PHP,
            )
            ->save()
        ;

        if (io()->confirm(\sprintf('We have created the "%s" migration file, do you want to execute it now?', $latestMigration))) {
            Database::migrate($app);
        } else {
            io()->caution('Do not forget to sync your database.');
        }

        Symfony::cacheClear($app);
        Assets::install($app);
    }

    private function createMediaLibraryMenu(App $app): void
    {
        $listener = $app->directory() . '/src/Menu/Admin/AdminMenuListener.php';

        fs()->dumpFile(
            $listener,
            <<<'PHP'
                <?php

                namespace App\Menu\Admin;

                use Knp\Menu\ItemInterface;
                use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
                use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

                #[AsEventListener(event: 'sylius.menu.admin.main')]
                final class AdminMenuListener
                {
                    public function __invoke(MenuBuilderEvent $event): void
                    {
                        $menu = $event->getMenu();

                        $this->addContentsSubMenu($menu);
                    }

                    private function addContentsSubMenu(ItemInterface $menu): void
                    {
                        $library = $menu
                            ->addChild('contents')
                            ->setLabel('Contents')
                            ->setLabelAttribute('icon', 'simple-icons:craftcms')
                            ->setExtra('always_open', true)
                        ;

                        $library->addChild('media_library', ['route' => 'joli_media_sylius_admin_explore'])
                            ->setLabel('media_library')
                            ->setExtra('translation_domain', 'JoliMediaSyliusBundle')
                        ;
                    }
                }

                PHP
        );
    }
}
