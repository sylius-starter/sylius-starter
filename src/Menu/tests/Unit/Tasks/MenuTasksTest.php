<?php

declare(strict_types=1);

namespace Unit\Tasks;

use Castor\Container;
use Castor\Sylius\PhpFile;
use Castor\Sylius\Tasks\MenuTasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(MenuTasks::class)]
final class MenuTasksTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/menu_tasks_test_' . uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir);
        $this->output = new BufferedOutput();

        $this->setUpContainer();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    private function setUpContainer(?SymfonyStyle $symfonyStyle = null): void
    {
        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();

        $setProperty = static function (Container $container, string $property, mixed $value): void {
            $prop = new \ReflectionProperty(Container::class, $property);
            $prop->setValue($container, $value);
        };

        $setProperty($container, 'fs', $this->filesystem);
        $setProperty($container, 'symfonyStyle', $symfonyStyle ?? new SymfonyStyle(new ArrayInput([]), $this->output));

        Container::set($container);
    }

    private function getRemoveFunction(MenuTasks $menuTasks): \Closure
    {
        $tasks = iterator_to_array($menuTasks->__invoke());

        return $tasks[0]['function'];
    }

    private function listenerFilePath(): string
    {
        return $this->tempDir . '/src/Menu/Admin/RemoveMenuItemsListener.php';
    }

    private function createListenerFile(array $items): void
    {
        $dir = \dirname($this->listenerFilePath());
        $this->filesystem->mkdir($dir);

        $hiddenItems = "[\n" . implode(",\n", array_map(static fn(string $item): string => "        '{$item}'", $items)) . "\n    ]";

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\Menu\Admin;

            use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
            use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

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

        $this->filesystem->dumpFile($this->listenerFilePath(), $content);
    }

    private function readRemovedMenuItems(): array
    {
        $filePath = $this->listenerFilePath();

        if (!$this->filesystem->exists($filePath)) {
            return [];
        }

        return (new PhpFile($filePath))->findClassConstant('REMOVED_MENU_ITEMS') ?? [];
    }

    private function getOutput(): string
    {
        return preg_replace('/\s+/', ' ', $this->output->fetch());
    }

    public function testInvokeReturnsTaskStructure(): void
    {
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $tasks = iterator_to_array($menuTasks->__invoke());

        static::assertCount(1, $tasks);
        static::assertArrayHasKey('task', $tasks[0]);
        static::assertArrayHasKey('function', $tasks[0]);
        static::assertInstanceOf(\Closure::class, $tasks[0]['function']);
    }

    public function testRemoveCreatesListenerWhenNoExistingFile(): void
    {
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['catalog/products']);

        static::assertTrue($this->filesystem->exists($this->listenerFilePath()));
        static::assertSame(['catalog/products'], $this->readRemovedMenuItems());
    }

    public function testRemoveMergesWithExistingItems(): void
    {
        $this->createListenerFile(['catalog/products']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['sales/orders']);

        static::assertTrue($this->filesystem->exists($this->listenerFilePath()));
        static::assertSame(['catalog/products', 'sales/orders'], $this->readRemovedMenuItems());
    }

    public function testRemoveWithReplaceOverwritesExistingItems(): void
    {
        $this->createListenerFile(['catalog/products']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['sales/orders'], true);

        static::assertTrue($this->filesystem->exists($this->listenerFilePath()));
        static::assertSame(['sales/orders'], $this->readRemovedMenuItems());
    }

    public function testReplaceAndRestoreTogetherShowsError(): void
    {
        $this->createListenerFile(['catalog/products']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['catalog/products'], true, true);

        static::assertStringContainsString('The --replace and --restore options cannot be used together.', $this->getOutput());
        static::assertSame(['catalog/products'], $this->readRemovedMenuItems());
    }

    public function testRestoreAllItemsRemovesListener(): void
    {
        $this->createListenerFile(['catalog/products', 'sales/orders']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['catalog/products', 'sales/orders'], false, true);

        static::assertFalse($this->filesystem->exists($this->listenerFilePath()));
    }

    public function testRestoreSomeItemsKeepsListenerWithRemaining(): void
    {
        $this->createListenerFile(['catalog/products', 'sales/orders']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['catalog/products'], false, true);

        static::assertTrue($this->filesystem->exists($this->listenerFilePath()));
        static::assertSame(['sales/orders'], $this->readRemovedMenuItems());
    }

    public function testRestoreWhenNoListenerShowsError(): void
    {
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['catalog/products'], false, true);

        static::assertStringContainsString('nothing to restore', $this->getOutput());
    }

    public function testRestoreNonMatchingItemsShowsWarning(): void
    {
        $this->createListenerFile(['sales/orders']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['catalog/products'], false, true);

        static::assertStringContainsString('The following items were not in the removed list: catalog/products', $this->getOutput());
    }

    public function testRestoreWhenNoMatchingItemsShowsComment(): void
    {
        $this->createListenerFile(['sales/orders']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['catalog'], false, true);

        static::assertStringContainsString('No matching items to restore.', $this->getOutput());
        static::assertSame(['sales/orders'], $this->readRemovedMenuItems());
    }

    public function testRestoreSingleItemFromListKeepsRemaining(): void
    {
        $this->createListenerFile(['catalog/products', 'sales/orders', 'customers/customers']);
        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $remove(['sales/orders'], false, true);

        static::assertTrue($this->filesystem->exists($this->listenerFilePath()));
        static::assertSame(['catalog/products', 'customers/customers'], $this->readRemovedMenuItems());
    }

    public function testRemoveWithEmptyItemsAsksForChoice(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('choice')
            ->willThrowException(new MissingInputException());

        $this->setUpContainer($io);

        $menuTasks = new MenuTasks('test-app', $this->tempDir);
        $remove = $this->getRemoveFunction($menuTasks);

        $this->expectException(MissingInputException::class);

        $remove([]);
    }
}
