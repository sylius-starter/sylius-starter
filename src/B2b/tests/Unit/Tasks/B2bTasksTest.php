<?php

declare(strict_types=1);

namespace Unit\Tasks;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Container;
use Castor\Sylius\Tasks\B2bTasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(B2bTasks::class)]
final class B2bTasksTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/b2b_tasks_test_' . uniqid();
        $this->setUpContainer();
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

    public function testRegistersEnableTask(): void
    {
        $tasks = iterator_to_array((new B2bTasks('test-app', $this->tempDir))());

        static::assertCount(1, $tasks);
        static::assertInstanceOf(AsTask::class, $tasks[0]['task']);
        static::assertSame('enable', $tasks[0]['task']->name);
        static::assertSame('sylius:b2b', $tasks[0]['task']->namespace);
        static::assertArrayHasKey('function', $tasks[0]);
    }

    public function testAcceptsMultipleFeaturesAsRawTokens(): void
    {
        $tasks = iterator_to_array((new B2bTasks('test-app', $this->tempDir))());
        $function = new \ReflectionFunction($tasks[0]['function']);
        $parameter = $function->getParameters()[0];

        static::assertSame('features', $parameter->getName());
        static::assertCount(1, $parameter->getAttributes(AsRawTokens::class));
    }

    public function testEmptyFeaturesAsksForChoice(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('choice')
            ->with(
                'Which B2B features would you like to enable?',
                [
                    'customer_validation' => 'customer_validation - Require admin approval before customers can sign in',
                    'hide_checkout' => 'hide_checkout - Hide cart and checkout for guests',
                    'hide_prices' => 'hide_prices - Hide product prices for guests',
                ],
                null,
                true,
            )
            ->willThrowException(new MissingInputException());

        $this->setUpContainer($io);

        $tasks = iterator_to_array((new B2bTasks('test-app', $this->tempDir))());

        $this->expectException(MissingInputException::class);

        $tasks[0]['function']([]);
    }
}
