<?php

declare(strict_types=1);

namespace SyliusStarter\Tests\Integration;

use Castor\Attribute\AsTask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SyliusStarter\Core\Installer\SyliusInstallerExtensions;
use SyliusStarter\Core\Service\SyliusService;
use SyliusStarter\Core\Task\TaskProviderRegistry;
use SyliusStarter\PaymentGateways\Installer\PaymentGatewaysSyliusInstallerExtension;

/**
 * Every package registers its tasks in the core TaskProviderRegistry from a
 * file autoloaded by Composer: once installed, the SyliusService exposes them.
 */
final class TaskRegistrationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function packageTaskProvider(): iterable
    {
        yield 'plugins' => ['plugins', 'sylius:plugin:add'];
        yield 'plugins remover' => ['plugins', 'sylius:plugin:remove'];
        yield 'payment gateways' => ['payment_gateways', 'sylius:payment-gateways:setup'];
        yield 'themes' => ['themes', 'sylius:theme:setup'];
        yield 'menu' => ['menu', 'sylius:menu:remove'];
        yield 'import list' => ['import', 'sylius:import:list'];
        yield 'import delete' => ['import', 'sylius:import:delete'];
        yield 'import ai build' => ['import', 'sylius:import:ai:build'];
        yield 'import fixtures generate' => ['import', 'sylius:import:fixtures:generate'];
        yield 'import fixtures load' => ['import', 'sylius:import:fixtures:load'];
        yield 'b2b' => ['b2b', 'sylius:b2b:enable'];
        yield 'upsun' => ['upsun', 'sylius:upsun:check'];
    }

    #[DataProvider('packageTaskProvider')]
    public function testPackageTasksAreExposedByTheSyliusService(string $provider, string $task): void
    {
        static::assertTrue(TaskProviderRegistry::has($provider));
        static::assertContains($task, $this->taskNames(new SyliusService()));
    }

    public function testPaymentGatewaysExtendTheSyliusInstaller(): void
    {
        static::assertInstanceOf(PaymentGatewaysSyliusInstallerExtension::class, SyliusInstallerExtensions::all()['payment_gateways'] ?? null);
    }

    /**
     * @return list<string>
     */
    private function taskNames(SyliusService $service): array
    {
        $names = [];

        foreach ($service->getTasks() as $task) {
            /** @var AsTask $asTask */
            $asTask = $task['task'];
            $names[] = $asTask->namespace . ':' . $asTask->name;
        }

        return $names;
    }
}
