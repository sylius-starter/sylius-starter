<?php

declare(strict_types=1);

namespace Unit\Tasks;

use Castor\Container;
use Castor\Sylius\Plugin\Installer\CmsInstaller;
use Castor\Sylius\Plugin\Installer\PluginInstaller;
use Castor\Sylius\Plugin\Installer\ProductBundleInstaller;
use Castor\Sylius\Plugin\Remover\ApiRemover;
use Castor\Sylius\Plugin\Remover\PluginRemover;
use Castor\Sylius\Tasks\PluginTasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(PluginTasks::class)]
final class PluginTasksTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/plugin_tasks_test_' . uniqid();
        $this->resetComponents();
        $this->setUpContainer();
    }

    /**
     * PluginTasks keeps its installers and removers in private statics, so they
     * have to be cleared between tests to keep them independent.
     */
    private function resetComponents(): void
    {
        (new \ReflectionProperty(PluginTasks::class, 'installers'))->setValue(null, []);
        (new \ReflectionProperty(PluginTasks::class, 'removers'))->setValue(null, []);
    }

    private function setUpContainer(?SymfonyStyle $symfonyStyle = null): void
    {
        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();

        $setProperty = static function (Container $container, string $property, mixed $value): void {
            $prop = new \ReflectionProperty(Container::class, $property);
            $prop->setValue($container, $value);
        };

        $setProperty($container, 'symfonyStyle', $symfonyStyle ?? new SymfonyStyle(new ArrayInput([]), new BufferedOutput()));

        Container::set($container);
    }

    public function testItLabelsChoicesWithTheDescription(): void
    {
        static::assertSame([
            'cms' => 'cms - CMS plugin for Sylius applications',
        ], PluginTasks::choices(['cms' => new CmsInstaller()]));
    }

    public function testItFallsBackToTheBareNameWithoutDescription(): void
    {
        static::assertSame([
            'empty_description' => 'empty_description',
            'without_description' => 'without_description',
        ], PluginTasks::choices([
            'without_description' => new PluginInstaller('without_description', static fn(): null => null),
            'empty_description' => new PluginInstaller('empty_description', static fn(): null => null, ''),
        ]));
    }

    public function testItSortsChoicesByName(): void
    {
        $choices = PluginTasks::choices([
            'wishlist' => new PluginInstaller('wishlist', static fn(): null => null, 'Wishlist plugin for Sylius'),
            'cms' => new CmsInstaller(),
            'api' => new PluginRemover('api', static fn(): null => null),
        ]);

        static::assertSame(['api', 'cms', 'wishlist'], array_keys($choices));
    }

    public function testItExposesADescriptionForEveryBuiltinPlugin(): void
    {
        PluginTasks::addInstaller(new CmsInstaller());
        PluginTasks::addInstaller(new ProductBundleInstaller());
        PluginTasks::addRemover(new ApiRemover());

        static::assertSame([
            'cms' => 'cms - CMS plugin for Sylius applications',
            'product_bundle' => 'product_bundle - Product bundle for Sylius',
        ], PluginTasks::choices((new \ReflectionProperty(PluginTasks::class, 'installers'))->getValue()));

        static::assertSame([
            'api' => 'api - Sylius API and its test tooling',
        ], PluginTasks::choices((new \ReflectionProperty(PluginTasks::class, 'removers'))->getValue()));
    }

    public function testItAsksForAChoiceWithDescriptionsWhenNoPluginIsGiven(): void
    {
        PluginTasks::addInstaller(new CmsInstaller());

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('choice')
            ->with(
                'Which plugins would you like to install?',
                ['cms' => 'cms - CMS plugin for Sylius applications'],
                null,
                true,
            )
            ->willThrowException(new MissingInputException());

        $this->setUpContainer($io);

        $tasks = iterator_to_array((new PluginTasks('test-app', $this->tempDir))());

        $this->expectException(MissingInputException::class);

        $tasks[0]['function']([]);
    }

    public function testItAsksForAChoiceWithDescriptionsWhenNoPluginIsGivenToRemove(): void
    {
        PluginTasks::addRemover(new ApiRemover());

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('choice')
            ->with(
                'Which plugins would you like to remove?',
                ['api' => 'api - Sylius API and its test tooling'],
                null,
                true,
            )
            ->willThrowException(new MissingInputException());

        $this->setUpContainer($io);

        $tasks = iterator_to_array((new PluginTasks('test-app', $this->tempDir))());

        $this->expectException(MissingInputException::class);

        $tasks[1]['function']([]);
    }
}
