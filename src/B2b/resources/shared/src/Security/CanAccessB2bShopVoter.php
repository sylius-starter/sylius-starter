<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class CanAccessB2bShopVoter extends Voter
{
    public const string ATTRIBUTE = 'CAN_ACCESS_B2B_SHOP';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return \is_object($user)
            && method_exists($user, 'isEnabled')
            && true === $user->isEnabled();
    }
}
