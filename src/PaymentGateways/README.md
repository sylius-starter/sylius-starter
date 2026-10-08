# sylius-starter/payment-gateways

Castor task to set up Sylius payment gateways (Mollie, PayPal, Stripe): `sylius:payment-gateways:setup`. Custom gateways can be declared with the `#[AsPaymentGatewayInstaller]` and `#[AsPaymentGatewayRemover]` attributes. It also adds a payment gateways question to `castor docker:service:install sylius`.

## Installation

In your Castor project:

```bash
castor composer require sylius-starter/payment-gateways
```

Or install every Sylius Starter package at once with `sylius-starter/sylius-starter`.

Read the [documentation](https://github.com/sylius-starter/sylius-starter#readme) for usage.

## Contributing

This repository is a **read-only split** of the
[sylius-starter/sylius-starter](https://github.com/sylius-starter/sylius-starter) monorepo.
Please open issues and pull requests there.
