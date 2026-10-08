# Repository guidance

## Theme screenshots

Install the theme you want to capture before running the script. From the Sylius
application, run `castor sylius:theme:setup <theme>` (for example,
`castor sylius:theme:setup canvas`). Theme setup switches the active theme; the
screenshot script only captures the storefront and verifies its theme marker.

To refresh the full-page homepage and Comet Pulse T-Shirt screenshots for a theme, run:

```bash
node scripts/capture-theme-screenshots.mjs canvas
node scripts/capture-theme-screenshots.mjs blush
node scripts/capture-theme-screenshots.mjs prompt_dark
node scripts/capture-theme-screenshots.mjs prompt_light
node scripts/capture-theme-screenshots.mjs volt
```

The script saves the screenshots in `docs/images/`. Use `--base-url <url>` to capture from another storefront URL, or `--output-dir <dir>` to choose another output directory. The default storefront is `https://app.test/en_US/`.

Add `--include-cart` to also put the Comet Pulse T-Shirt in a fresh browser cart and capture the cart page:

```bash
node scripts/capture-theme-screenshots.mjs canvas --include-cart
node scripts/capture-theme-screenshots.mjs blush --include-cart
node scripts/capture-theme-screenshots.mjs prompt_dark --include-cart
node scripts/capture-theme-screenshots.mjs prompt_light --include-cart
node scripts/capture-theme-screenshots.mjs volt --include-cart
```

The cart uses an isolated temporary browser profile and does not alter another browser session's cart.

The script requires Node.js 22+ and Google Chrome or Chromium. Set `CHROME_BIN` if the browser executable is not detected automatically. The selected theme must be active on the storefront.

## Running commands in the Sylius app

Run Symfony console commands through the Castor app container from the repository
root:

```bash
castor app:bash -- php bin/console cache:clear --env=dev
castor app:bash -- php bin/console lint:twig templates --env=test
```

To switch the active storefront theme, use:

```bash
castor sylius:theme:setup <theme>
```

## Theme styling

Never rely on `data-test-*` attributes in CSS selectors. They are testing
helpers and may not be present in the production storefront; target stable
classes or semantic structure instead.
