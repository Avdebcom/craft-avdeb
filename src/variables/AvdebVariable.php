<?php

namespace avdeb\craftavdeb\variables;

use avdeb\craftavdeb\Plugin;
use avdeb\craftavdeb\twig\Extension;
use Twig\Markup;

/**
 * `craft.avdeb`: the Twig functions, plus `craft.avdeb.data()` for your own markup.
 *
 *   {% set result = craft.avdeb.data({ tag: 'stickers', limit: 4 }) %}
 *   {% for card in result.cards %}<a href="{{ card.href }}" rel="{{ card.rel }}">{{ card.title }}</a>{% endfor %}
 */
class AvdebVariable
{
    public function products(array|string $options = []): Markup
    {
        return Extension::products($options);
    }

    public function product(array|string $handle = [], array $options = []): Markup
    {
        return Extension::product($handle, $options);
    }

    public function button(array|string $options = []): Markup
    {
        return Extension::button($options);
    }

    public function parse(mixed $html): Markup
    {
        return Extension::filter($html);
    }

    /**
     * Cards (href with your referral code, rel, target, title, image, width, height, price, compareAt,
     * discount, isNew, creator), the raw products, the "view all" link and the disclosure text.
     */
    public function data(array|string $options = []): array
    {
        $attrs = is_string($options) ? ['url' => $options] : $options;

        return Plugin::getInstance()->embeds->data($attrs);
    }

    /** The referral code product links carry ("" without one). */
    public function ref(): string
    {
        return Plugin::getInstance()->embeds->status()['ref'];
    }
}
