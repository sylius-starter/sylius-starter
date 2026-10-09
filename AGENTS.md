# Repository guidance

## Theme screenshots

Install the theme you want to capture before running the script. From the Sylius
application, run `castor sylius:theme:setup <theme>` (for example,
`castor sylius:theme:setup canvas`). Theme setup switches the active theme; the
screenshot script only captures the storefront and verifies its theme marker.

To refresh the full-page homepage and product page screenshots for a theme, run:

```bash
node scripts/capture-theme-screenshots.mjs canvas
node scripts/capture-theme-screenshots.mjs blush
node scripts/capture-theme-screenshots.mjs prompt_dark
node scripts/capture-theme-screenshots.mjs prompt_light
node scripts/capture-theme-screenshots.mjs volt
node scripts/capture-theme-screenshots.mjs lagoon
```

The script saves the screenshots in `docs/images/` (`<theme>-homepage.png`, `<theme>-product.png` and `<theme>-cart.png`). Use `--base-url <url>` to capture from another storefront URL, or `--output-dir <dir>` to choose another output directory. The default storefront is `https://app.test/en_US/`.

Data fixtures differ between installs, so the script evaluates the products listed on the storefront
(homepage, then category pages) and picks the first one that is in stock, has an add-to-cart button and a real
image, preferring the Comet Pulse T-Shirt when it qualifies. Skipped products are listed with the reason. Use
`--product <slug>` to force a product.

Add `--include-cart` to also put the selected product in a fresh browser cart and capture the cart page:

```bash
node scripts/capture-theme-screenshots.mjs canvas --include-cart
node scripts/capture-theme-screenshots.mjs blush --include-cart
node scripts/capture-theme-screenshots.mjs prompt_dark --include-cart
node scripts/capture-theme-screenshots.mjs prompt_light --include-cart
node scripts/capture-theme-screenshots.mjs volt --include-cart
node scripts/capture-theme-screenshots.mjs lagoon --include-cart
```

The cart uses an isolated temporary browser profile and does not alter another browser session's cart.

To refresh the screenshots of several themes in one go, run from the repository root:

```bash
castor docs:screenshots                        # asks which themes to capture ("all" by default)
castor docs:screenshots canvas volt            # only these themes
castor docs:screenshots all --restore=canvas   # every theme, then switch back to canvas
```

For each theme, the task runs `castor sylius:theme:setup <theme>` then the screenshot script with `--include-cart`
(`--skip-cart` to leave the cart out), and stops at the first failure. Without `--restore`, the last captured theme
stays active.

The script hides the Symfony web debug toolbar, so the app can stay in the `dev` environment.

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

## Monorepo

The code is split into packages under `src/<Package>/` (`composer.json`, `src/`, `tests/`, `resources/`). The root
`composer.json` is generated: after changing a package `composer.json`, run `castor monorepo:merge`, then check with
`castor monorepo:validate`. A package must never use another task package's namespace, only `SyliusStarter\Core`.

## Storefront slots

The homepage hero is a shared slot owned by core: themes ship `templates/shop/homepage/hero.html.twig`, packages feed
data through `App\Storefront\Hero\HeroContributorInterface`, and only core configures the `banner`/`hero` hookables.
Prefix any hookable a package adds with the package name. See `docs/storefront/hero.md`.
