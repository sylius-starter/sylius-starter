<?php

declare(strict_types=1);

namespace Unit;

use Castor\Container;
use Castor\Sylius\PhpFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(PhpFile::class)]
final class PhpFileTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php_file_test_' . uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir);

        $this->setUpContainer();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    private function setUpContainer(): void
    {
        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();

        $prop = new \ReflectionProperty(Container::class, 'fs');
        $prop->setValue($container, $this->filesystem);

        Container::set($container);
    }

    public function testAddScalarConstants(): void
    {
        $filePath = $this->createFilePath();

        $file = new PhpFile($filePath);
        $file->addClassConstant(<<<'PHP'
            const string FOO = 'bar';
            const int BAR = 42;
            const bool BAZ = true;
            PHP);
        $file->save();

        $content = file_get_contents($filePath);

        static::assertStringContainsString('const string FOO = \'bar\';', $content);
        static::assertStringContainsString('const int BAR = 42;', $content);
        static::assertStringContainsString('const bool BAZ = true;', $content);

        $reloaded = new PhpFile($filePath);

        static::assertSame('bar', $reloaded->findClassConstant('FOO'));
        static::assertSame(42, $reloaded->findClassConstant('BAR'));
        static::assertTrue($reloaded->findClassConstant('BAZ'));
    }

    public function testAddArrayConstantPreservesKeys(): void
    {
        $filePath = $this->createFilePath();

        $value = [
            'catalog/products',
            'label' => 'Products',
            'sales/orders',
        ];

        (new PhpFile($filePath))
            ->addClassConstant(<<<'PHP'
                const array REMOVED_MENU_ITEMS = ['catalog/products', 'label' => 'Products', 'sales/orders'];
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertStringContainsString('const array REMOVED_MENU_ITEMS = [', $content);

        $reloaded = new PhpFile($filePath);

        static::assertSame($value, $reloaded->findClassConstant('REMOVED_MENU_ITEMS'));
    }

    public function testAddClassConstantIsIdempotent(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addClassConstant("const string FOO = 'first';")
            ->addClassConstant("const string FOO = 'second';")
            ->save();

        $content = file_get_contents($filePath);

        static::assertSame(1, substr_count($content, 'FOO'));
        static::assertStringContainsString("const string FOO = 'first';", $content);
        static::assertStringNotContainsString("const string FOO = 'second';", $content);
    }

    #[DataProvider('provideClassConstantCode')]
    public function testAddClassConstantPreservesVisibility(string $code, string $expected): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addClassConstant($code)
            ->save();

        $content = file_get_contents($filePath);

        static::assertStringContainsString($expected, $content);
    }

    public static function provideClassConstantCode(): iterable
    {
        yield 'public' => ["public const string FOO = 'bar';", "public const string FOO = 'bar';"];
        yield 'protected' => ["protected const string FOO = 'bar';", "protected const string FOO = 'bar';"];
        yield 'private' => ["private const string FOO = 'bar';", "private const string FOO = 'bar';"];
        yield 'implicit public' => ["const string FOO = 'bar';", "const string FOO = 'bar';"];
    }

    public function testAddProperty(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addProperty(<<<'PHP'
                #[ORM\Column(type: Types::STRING, length: 30)]
                private string $state = self::STATE_NEW;
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertStringContainsString('#[ORM\Column(type: Types::STRING, length: 30)]', $content);
        static::assertStringContainsString('private string $state = self::STATE_NEW;', $content);
    }

    public function testAddPropertyWithMultipleProperties(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addProperty(<<<'PHP'
                #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => self::STATE_NEW])]
                private string $state = self::STATE_NEW;

                #[ORM\Column(type: Types::STRING, length: 255)]
                private string $comment = '';
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertIsString($content);
        static::assertStringContainsString('#[ORM\Column(type: Types::STRING, length: 30, options: [\'default\' => self::STATE_NEW])]', $content);
        static::assertStringContainsString('private string $state = self::STATE_NEW;', $content);
        static::assertStringContainsString('#[ORM\Column(type: Types::STRING, length: 255)]', $content);
        static::assertStringContainsString('private string $comment = \'\';', $content);
    }

    public function testAddPropertyWithDuplicateAmongMultipleIsIdempotent(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addProperty(<<<'PHP'
                private string $state = self::STATE_NEW;
                PHP)
            ->addProperty(<<<'PHP'
                private string $state = self::STATE_ACCEPTED;

                private string $comment = '';
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertIsString($content);
        static::assertSame(1, substr_count($content, '$state'));
        static::assertStringContainsString('private string $state = self::STATE_NEW;', $content);
        static::assertStringNotContainsString('self::STATE_ACCEPTED', $content);
        static::assertStringContainsString('private string $comment = \'\';', $content);
    }

    public function testAddPropertyIsIdempotent(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addProperty(<<<'PHP'
                private string $state = self::STATE_NEW;
                PHP)
            ->addProperty(<<<'PHP'
                private string $state = self::STATE_ACCEPTED;
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertSame(1, substr_count($content, '$state'));
        static::assertStringContainsString('private string $state = self::STATE_NEW;', $content);
        static::assertStringNotContainsString('self::STATE_ACCEPTED', $content);
    }

    public function testAddPropertyWithNoClassReturnsThis(): void
    {
        $filePath = $this->tempDir . '/no_class.php';

        $this->filesystem->dumpFile($filePath, "<?php\n\n\$foo = 'bar';\n");

        $file = new PhpFile($filePath);

        static::assertSame($file, $file->addProperty('private string $state = self::STATE_NEW;'));

        $file->save();

        $saved = file_get_contents($filePath);

        static::assertIsString($saved);
        static::assertStringNotContainsString('$state', $saved);
    }

    public function testAddAttribute(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addImport('Sylius\Resource\Metadata\ApplyStateMachineTransition')
            ->addImport('Sylius\Resource\Metadata\AsResource')
            ->addAttribute(<<<'PHP'
                #[AsResource(
                    section: 'admin',
                    routePrefix: '/%sylius_admin.path_name%',
                    operations: [
                        new ApplyStateMachineTransition(
                            redirectToRoute: 'sylius_admin_customer_update',
                            stateMachineTransition: 'accept',
                        ),
                        new ApplyStateMachineTransition(
                            redirectToRoute: 'sylius_admin_customer_update',
                            stateMachineTransition: 'reject',
                        ),
                    ],
                )]
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertIsString($content);
        static::assertStringContainsString('use Sylius\Resource\Metadata\ApplyStateMachineTransition;', $content);
        static::assertStringContainsString('use Sylius\Resource\Metadata\AsResource;', $content);
        static::assertStringContainsString('#[AsResource(', $content);
        static::assertStringContainsString("routePrefix: '/%sylius_admin.path_name%'", $content);
        static::assertStringContainsString("stateMachineTransition: 'accept'", $content);
        static::assertStringContainsString("stateMachineTransition: 'reject'", $content);
    }

    public function testAddAttributeIsIdempotent(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addAttribute(<<<'PHP'
                #[AsResource(section: 'admin')]
                PHP)
            ->addAttribute(<<<'PHP'
                #[AsResource(section: 'shop')]
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertIsString($content);
        static::assertSame(1, substr_count($content, '#[AsResource('));
        static::assertStringContainsString("#[AsResource(section: 'admin')]", $content);
        static::assertStringNotContainsString("section: 'shop'", $content);
    }

    public function testAddAttributeWithNoClassReturnsThis(): void
    {
        $filePath = $this->tempDir . '/no_class.php';

        $this->filesystem->dumpFile($filePath, "<?php\n\n\$foo = 'bar';\n");

        $file = new PhpFile($filePath);

        static::assertSame($file, $file->addAttribute("#[AsResource(section: 'admin')]"));

        $file->save();

        $saved = file_get_contents($filePath);

        static::assertIsString($saved);
        static::assertStringNotContainsString('AsResource', $saved);
    }

    public function testAddMethod(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addMethod(<<<'PHP'
                public function getState(): string
                {
                    return $this->state;
                }

                public function setState(string $state): void
                {
                    $this->state = $state;
                }
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertIsString($content);
        static::assertStringContainsString('public function getState(): string', $content);
        static::assertStringContainsString('return $this->state;', $content);
        static::assertStringContainsString('public function setState(string $state): void', $content);
        static::assertStringContainsString('$this->state = $state;', $content);
    }

    public function testAddMethodIsIdempotent(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addMethod(<<<'PHP'
                public function getState(): string
                {
                    return $this->state;
                }

                public function setState(string $state): void
                {
                    $this->state = $state;
                }
                PHP)
            ->addMethod(<<<'PHP'
                public function getState(): string
                {
                    return $this->other;
                }

                public function foo(): void
                {
                }
                PHP)
            ->save();

        $content = file_get_contents($filePath);

        static::assertIsString($content);
        static::assertSame(1, substr_count($content, 'function getState'));
        static::assertStringContainsString('return $this->state;', $content);
        static::assertStringNotContainsString('return $this->other;', $content);
        static::assertStringContainsString('public function foo(): void', $content);
    }

    public function testAddMethodWithNoClassReturnsThis(): void
    {
        $filePath = $this->tempDir . '/no_class.php';

        $this->filesystem->dumpFile($filePath, "<?php\n\n\$foo = 'bar';\n");

        $file = new PhpFile($filePath);

        static::assertSame($file, $file->addMethod('public function getState(): string' . "\n" . '{' . "\n" . '}'));

        $file->save();

        $saved = file_get_contents($filePath);

        static::assertIsString($saved);
        static::assertStringNotContainsString('function', $saved);
    }

    public function testAddImportWithAlias(): void
    {
        $filePath = $this->createFilePath();

        (new PhpFile($filePath))
            ->addImport('Doctrine\\ORM\\Mapping', 'ORM')
            ->addImport('Doctrine\\ORM\\Mapping', 'ORM')
            ->save();

        $content = file_get_contents($filePath);

        static::assertSame(1, substr_count($content, 'use Doctrine\\ORM\\Mapping as ORM;'));
    }

    public function testAddImportWithAliasWhenAlreadyPresent(): void
    {
        $filePath = $this->tempDir . '/App/ImportAliasClass.php';

        $this->filesystem->mkdir(\dirname($filePath));

        $content = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App;

            use Doctrine\ORM\Mapping as ORM;

            final class ImportAliasClass
            {
            }

            PHP;

        $this->filesystem->dumpFile($filePath, $content);

        (new PhpFile($filePath))
            ->addImport('Doctrine\\ORM\\Mapping', 'ORM')
            ->addImport('Doctrine\\DBAL\\Types\\Types')
            ->save();

        $saved = file_get_contents($filePath);

        static::assertIsString($saved);
        static::assertSame(1, substr_count($saved, 'use Doctrine\\ORM\\Mapping as ORM;'));
        static::assertStringContainsString('use Doctrine\\DBAL\\Types\\Types;', $saved);
    }

    public function testAddClassConstantWithNoClassReturnsThis(): void
    {
        $filePath = $this->tempDir . '/no_class.php';

        $content = "<?php\n\ndeclare(strict_types=1);\n\n\$foo = 'bar';\n";
        $this->filesystem->dumpFile($filePath, $content);

        $file = new PhpFile($filePath);

        static::assertSame($file, $file->addClassConstant("const string FOO = 'bar';"));

        $file->save();

        $saved = file_get_contents($filePath);

        static::assertIsString($saved);
        static::assertStringNotContainsString('const', $saved);
        static::assertStringNotContainsString('FOO', $saved);
        static::assertStringContainsString("\$foo = 'bar';", $saved);
    }

    private function createFilePath(): string
    {
        $filePath = $this->tempDir . '/App/TestClass.php';

        $this->filesystem->mkdir(\dirname($filePath));

        $content = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App;

            final class TestClass
            {
            }

            PHP;

        $this->filesystem->dumpFile($filePath, $content);

        return $filePath;
    }
}
