# Homepage hero slot

The homepage hero is a slot owned by `sylius-starter/core`, so that themes and other packages (import...) can
all use it without overwriting each other.

| Who        | Provides | How |
|------------|----------|-----|
| Core       | the slot | `SyliusStarter\Core\Storefront\Hero::install($app)` (idempotent) generates the `StorefrontHero` Twig component, disables the native `banner` hookable and registers `hero` on `sylius_shop.homepage.index` (`config/sylius/storefront/hero.yaml`). |
| A theme    | markup   | ships `templates/shop/homepage/hero.html.twig`, picked up automatically. No hook config for the hero. |
| A package  | data     | generates a service implementing `App\Storefront\Hero\HeroContributorInterface` (autoconfigured). |
| The project| either   | its own contributor, or a custom template through the `template` prop of the `hero` hookable. |

Template resolution: `template` prop, then `shop/homepage/hero.html.twig` (theme), then
`storefront/hero/default.html.twig` (core fallback, which renders the native Sylius banner when no data is contributed).

## Writing a theme template

The template receives `hero` (`App\Storefront\Hero\HeroContent`, every field nullable). Use contributed data when present
and fall back on the theme's own copy:

```twig
<h1>{% if hero.title %}{{ hero.title }}{% else %}{{ 'my_theme.hero.title'|trans({}, 'my_theme')|raw }}{% endif %}</h1>

{% if hero.image %}
    <img class="my-theme-hero__image" src="{{ hero.image }}" alt="{{ hero.imageAlt ?? '' }}">
{% else %}
    {# theme artwork #}
{% endif %}
```

## Writing a contributor

```php
#[AsTaggedItem(priority: 100)] // higher runs first, later contributors can override
final class MyHeroContributor implements HeroContributorInterface
{
    public function contribute(HeroContent $hero): HeroContent
    {
        return $hero->merge(new HeroContent(title: 'Summer sale'));
    }
}
```

## Hook rules

- Only core configures the `banner` and `hero` hookables of `sylius_shop.homepage.index`
  (checked by `tests/Integration/StorefrontHeroContractTest.php`).
- Themes may disable native Sylius hookables they do not want (`latest_deals`, `new_collection`...).
- Hookables a package adds are prefixed with its name (`import_collections`, `theme_promise`...), so a theme disabling a
  native block never hides another package's content.
- Every theme renders a promise block (`templates/shop/homepage/promise.html.twig`) through the shared `theme_promise`
  hookable, priority 150 (between the hero and the latest products). Only themes declare `theme_*` hookables
  (checked by `tests/Integration/ThemePromiseContractTest.php`).
