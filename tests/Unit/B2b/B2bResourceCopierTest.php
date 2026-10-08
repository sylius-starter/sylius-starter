<?php

declare(strict_types=1);

namespace Unit\B2b;

use Castor\Container;
use Castor\Sylius\App;
use Castor\Sylius\B2b\B2bResourceCopier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(B2bResourceCopier::class)]
final class B2bResourceCopierTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/b2b_resource_copier_test_' . uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir);

        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(Container::class, 'fs');
        $property->setValue($container, $this->filesystem);
        $property = new \ReflectionProperty(Container::class, 'symfonyStyle');
        $property->setValue($container, new SymfonyStyle(new ArrayInput([]), new BufferedOutput()));
        Container::set($container);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testCopiesFeatureResources(): void
    {
        B2bResourceCopier::copy(new App('test-app', $this->tempDir), 'hide_prices');

        static::assertFileExists($this->tempDir . '/config/sylius/twig_hooks/shop/product/hide_prices.php');
        static::assertFileExists($this->tempDir . '/templates/shop/product/common/price.html.twig');
        static::assertFileExists($this->tempDir . '/templates/shop/product/show/content/info/summary/prices/price.html.twig');
    }

    public function testCopiesCustomerValidationEmailResources(): void
    {
        $existingTranslations = $this->tempDir . '/translations/messages.en.yaml';
        $this->filesystem->dumpFile($existingTranslations, "existing:\n    key: Preserve this translation\n");
        $existingFlashTranslations = $this->tempDir . '/translations/flashes.en.yaml';
        $this->filesystem->dumpFile($existingFlashTranslations, "existing:\n    key: Preserve this flash translation\n");

        B2bResourceCopier::copy(new App('test-app', $this->tempDir), 'customer_validation');

        static::assertFileExists($this->tempDir . '/config/packages/sylius_mailer.yaml');
        static::assertFileExists($this->tempDir . '/src/EventListener/SendPendingRegistrationEmailListener.php');
        static::assertFileExists($this->tempDir . '/templates/email/customer_validation_registration.html.twig');
        static::assertFileExists($this->tempDir . '/config/sylius/twig_hooks/shop/account/register_thank_you.php');
        static::assertFileExists($this->tempDir . '/templates/shop/account/register/thank_you/title.html.twig');
        static::assertFileExists($this->tempDir . '/templates/shop/account/register/thank_you/subtitle.html.twig');
        static::assertFileExists($this->tempDir . '/translations/messages.fr.yaml');
        static::assertFileExists($this->tempDir . '/translations/flashes.fr.yaml');
        static::assertStringContainsString(
            '{% block subject %}',
            $this->filesystem->readFile($this->tempDir . '/templates/email/customer_validation_registration.html.twig'),
        );
        static::assertStringContainsString(
            '{% block body %}',
            $this->filesystem->readFile($this->tempDir . '/templates/email/customer_validation_registration.html.twig'),
        );

        $translations = \Symfony\Component\Yaml\Yaml::parseFile($existingTranslations);

        static::assertSame('Preserve this translation', $translations['existing']['key']);
        static::assertSame(
            'Thank you for registering',
            $translations['sylius']['email']['customer_validation']['registration']['title'],
        );

        $flashTranslations = \Symfony\Component\Yaml\Yaml::parseFile($existingFlashTranslations);

        static::assertSame('Preserve this flash translation', $flashTranslations['existing']['key']);
        static::assertSame(
            'Your account is awaiting approval by an administrator. You will be able to sign in once it has been approved.',
            $flashTranslations['sylius']['customer']['register'],
        );
    }
}
