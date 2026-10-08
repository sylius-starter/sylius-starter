<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\CanAccessB2bShopVoter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsEventListener(event: 'kernel.controller')]
final readonly class RequireB2bShopAccessListener
{
    public function __construct(private AuthorizationCheckerInterface $authorizationChecker) {}

    public function __invoke(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');

        if (!\is_string($route) || !$this->isCartOrCheckoutRoute($route)) {
            return;
        }

        if (!$this->authorizationChecker->isGranted(CanAccessB2bShopVoter::ATTRIBUTE)) {
            throw new AccessDeniedHttpException('An enabled customer account is required to access the cart and checkout.');
        }
    }

    private function isCartOrCheckoutRoute(string $route): bool
    {
        return str_starts_with($route, 'sylius_shop_cart_')
            || str_starts_with($route, 'sylius_shop_checkout_');
    }
}
