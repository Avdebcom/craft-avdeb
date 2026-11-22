<?php

namespace avdeb\craftavdeb\twig;

use avdeb\craftavdeb\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig functions and the `|avdeb` filter.
 *
 *   {{ avdebProducts({ url: 'https://avdeb.com/category/stickers/', layout: 'carousel' }) }}
 *   {{ avdebProduct('cat-sticker', { variant: 'hero', badge: "Editor's pick" }) }}
 *   {{ avdebButton({ href: '/creators/cleo/', label: 'Visit my shop' }) }}
 *   {{ entry.body|avdeb }}
 */
class Extension extends AbstractExtension
{
    public function getName(): string
    {
        return 'AVDEB Products';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('avdebProducts', [self::class, 'products']),
            new TwigFunction('avdebProduct', [self::class, 'product']),
            new TwigFunction('avdebButton', [self::class, 'button']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('avdeb', [self::class, 'filter']),
        ];
    }

    /**
     * Products from a creator, category, product type, search, or the newest ones.
     *
     * @param array|string $options Options, or an avdeb.com link / search words.
     */
    public static function products(array|string $options = []): Markup
    {
        $attrs = is_string($options) ? ['url' => $options] : $options;

        return self::markup(Plugin::getInstance()->embeds->render($attrs));
    }

    /**
     * One featured product.
     *
     * @param array|string $handle A product handle or avdeb.com/store/… link, or all options.
     */
    public static function product(array|string $handle = [], array $options = []): Markup
    {
        $attrs = is_string($handle) ? ['handle' => $handle] + $options : $handle + $options;
        $attrs['mode'] = 'product';
        if (isset($attrs['handle']) && !isset($attrs['value'])) {
            $attrs['value'] = $attrs['handle'];
        }

        return self::markup(Plugin::getInstance()->embeds->render($attrs));
    }

    /**
     * A referral button.
     *
     * @param array|string $options Options, or an avdeb.com link / path.
     */
    public static function button(array|string $options = []): Markup
    {
        $attrs = is_string($options) ? ['href' => $options] : $options;

        return self::markup(Plugin::getInstance()->embeds->button($attrs));
    }

    /** Replace AVDEB tags in rich text: `{{ entry.body|avdeb }}`. */
    public static function filter(mixed $html): Markup
    {
        return self::markup(Plugin::getInstance()->embeds->replaceTags((string) $html));
    }

    private static function markup(string $html): Markup
    {
        return new Markup($html, 'UTF-8');
    }
}
