<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Tito10047\AltchaBundle\Type\AltchaType;
use Tito10047\AltchaBundle\Validator\Altcha;

final class AltchaCustomerRegistrationTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('altcha', AltchaType::class, [
            'label' => false,
            // Sylius validates the registration form with the "sylius" group, not "Default"
            'constraints' => new Altcha(groups: ['sylius']),
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        yield CustomerRegistrationType::class;
    }
}
