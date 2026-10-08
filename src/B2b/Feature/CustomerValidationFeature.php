<?php

declare(strict_types=1);

namespace Castor\Sylius\B2b\Feature;

use Castor\Sylius\App;
use Castor\Sylius\B2b\B2bResourceCopier;
use Castor\Sylius\Feature\FeatureInterface;
use Castor\Sylius\PhpFile;
use Castor\Sylius\Util\Database;
use Castor\Sylius\Util\Filesystem;
use Castor\Sylius\Util\Yaml;

use function Castor\io;

final readonly class CustomerValidationFeature implements FeatureInterface
{
    public function name(): string
    {
        return 'customer_validation';
    }

    public function description(): string
    {
        return 'Require admin approval before customers can sign in';
    }

    public function __invoke(App $app): void
    {
        Yaml::import($app, 'config/packages/_sylius.yaml', '../sylius/workflows/**/**.php');
        B2bResourceCopier::copy($app, $this->name());

        (new PhpFile($app->directory() . '/src/Entity/Customer/Customer.php'))
            ->addImport('Doctrine\ORM\Mapping', 'ORM')
            ->addImport('Doctrine\DBAL\Types\Types')
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
            ->addClassConstant(<<<'PHP'
                public const string STATE_NEW = 'new';
                public const string STATE_ACCEPTED = 'accepted';
                public const string STATE_REJECTED = 'rejected';
                PHP)
            ->addProperty(<<<'PHP'
                #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => self::STATE_NEW])]
                private string $state = self::STATE_NEW;

                #[ORM\Column(type: Types::STRING, length: 12, nullable: true)]
                private ?string $localeCode = null;

                #[ORM\Column(type: Types::STRING, nullable: true)]
                private ?string $registrationChannel = null;
                PHP)
            ->addMethod(<<<'PHP'
                public function getState(): string
                {
                    return $this->state;
                }

                public function setState(string $state): void
                {
                    $this->state = $state;
                }

                public function getLocaleCode(): ?string
                {
                    return $this->localeCode;
                }

                public function setLocaleCode(?string $localeCode): void
                {
                    $this->localeCode = $localeCode;
                }

                public function getRegistrationChannel(): ?string
                {
                    return $this->registrationChannel;
                }

                public function setRegistrationChannel(?string $registrationChannel): void
                {
                    $this->registrationChannel = $registrationChannel;
                }
                PHP)
            ->save()
        ;

        try {
            Database::diff($app);

            $latestMigration = Filesystem::latestFile($app, 'migrations');

            if (null === $latestMigration) {
                io()->error('No latest migration found.');

                return;
            }

            if (io()->confirm(\sprintf('We have created the "%s" migration file, do you want to execute it now?', $latestMigration))) {
                Database::migrate($app);
            } else {
                io()->caution('Do not forget to sync your database.');
            }
        } catch (\Throwable) {
            io()->info('Your database seems to be already up to date.');
        }

        io()->success('Admin validation for customers has been created successfully.');
    }
}
