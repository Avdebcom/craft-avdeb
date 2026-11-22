<?php

namespace avdeb\craftavdeb\services;

use avdeb\craftavdeb\assets\EmbedAsset;
use avdeb\craftavdeb\lib\Catalog;
use avdeb\craftavdeb\lib\Client;
use avdeb\craftavdeb\lib\Options;
use avdeb\craftavdeb\lib\Renderer;
use avdeb\craftavdeb\lib\Settings as LibSettings;
use avdeb\craftavdeb\lib\Shortcodes;
use avdeb\craftavdeb\lib\View;
use avdeb\craftavdeb\Plugin;
use avdeb\craftavdeb\stores\CraftCacheStore;
use Craft;
use yii\base\Component;

/**
 * Settings, a cached catalog client and rendering, shared by the Twig functions, the `|avdeb` filter,
 * `craft.avdeb` and the settings page. Identical embeds on one page share one lookup.
 */
class Embeds extends Component
{
    private ?array $settings = null;
    private ?Client $client = null;

    /** @var array<string, array> Products per query within this request. */
    private array $memo = [];

    /** Forget settings and the client (after the settings were saved). */
    public function reset(): void
    {
        $this->settings = null;
        $this->client = null;
        $this->memo = [];
    }

    /**
     * Validated settings for the shared library. Never contains anything the visitor can see
     * except display options and the referral code.
     */
    public function settings(): array
    {
        return $this->settings ??= LibSettings::fromParams(Plugin::getInstance()->getSettings()->params());
    }

    public function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }
        $guzzle = Craft::createGuzzleClient();
        $http = static function (string $url, array $headers, int $timeout) use ($guzzle): array {
            $response = $guzzle->request('GET', $url, [
                'headers' => $headers,
                'timeout' => $timeout,
                'connect_timeout' => min(5, $timeout),
                'http_errors' => false,
            ]);

            return ['status' => $response->getStatusCode(), 'body' => (string) $response->getBody()];
        };
        $s = $this->settings();

        return $this->client = new Client(new CraftCacheStore(), $http, $s['apiKey'], $s['refCode'], $s['cacheMinutes']);
    }

    /** Language of the current site, for price formatting ("en-US", "de"…). */
    public static function locale(): string
    {
        try {
            return Craft::$app->getSites()->getCurrentSite()->language ?: 'en-US';
        } catch (\Throwable) {
            return 'en-US';
        }
    }

    /** Products for a query, de-duplicated within the request. Never throws. */
    public function lookup(array $query): array
    {
        $key = Catalog::queryKey($query);
        if (!isset($this->memo[$key])) {
            try {
                $this->memo[$key] = $this->client()->products($query);
            } catch (\Throwable $e) {
                Craft::warning('AVDEB catalog request failed: ' . $e->getMessage(), 'avdeb');
                $this->memo[$key] = ['products' => [], 'ref' => '', 'tier' => 'anonymous', 'source' => 'empty'];
            }
        }

        return $this->memo[$key];
    }

    /**
     * HTML for a collection (`mode` "collection") or one product (`mode` "product"). Never throws:
     * problems become an HTML comment so a page never breaks because of an embed.
     */
    public function render(array $attrs, ?int $headingLevel = null): string
    {
        try {
            $settings = $this->settings();
            $level = $headingLevel ?? (int) ($attrs['headingLevel'] ?? $attrs['heading-level'] ?? $attrs['heading_level'] ?? 2);
            $o = Options::parse($attrs, $settings['display']);
            if ($o['mode'] === 'product' && Options::productHandle($o['value']) === '') {
                return '<!-- AVDEB Product: add a product handle or link, e.g. avdebProduct("cat-sticker") -->';
            }
            $query = Catalog::sanitizeQuery(Options::queryFor($o), Catalog::maxLimit($settings['apiKey'] !== ''));
            $out = $this->lookup($query);
            $this->registerAssets();

            return Renderer::embed([
                'products' => $out['products'],
                'ref' => $out['ref'],
                'query' => $query,
                'disclosure' => LibSettings::disclosureFor($settings, $out['ref']),
            ], $o, self::locale(), max(2, min(5, $level ?: 2)));
        } catch (\Throwable $e) {
            Craft::warning('AVDEB embed failed: ' . $e->getMessage(), 'avdeb');

            return '<!-- AVDEB Products: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . ' -->';
        }
    }

    /** HTML for a referral button. */
    public function button(array $attrs): string
    {
        $settings = $this->settings();
        try {
            $ref = $this->client()->ref() ?: $settings['refCode'];
        } catch (\Throwable) {
            $ref = $settings['refCode'];
        }
        $this->registerAssets();

        return Renderer::button(Options::button($attrs), $ref, $settings['display']);
    }

    /** Replace `{avdeb-products …}`, `{avdeb-product …}` and `{avdeb-button …}` tags in HTML. */
    public function replaceTags(string $html): string
    {
        if (!Shortcodes::contains($html)) {
            return $html;
        }

        return Shortcodes::replace($html, function (string $tag, array $attrs): string {
            if ($tag === 'button') {
                return $this->button($attrs);
            }
            if ($tag === 'product') {
                $attrs['mode'] = 'product';
            }

            return $this->render($attrs);
        });
    }

    /**
     * Headless: product cards for your own markup.
     *
     * @return array{cards: array, products: array, ref: string, viewAll: ?string, disclosure: ?string}
     */
    public function data(array $attrs): array
    {
        $settings = $this->settings();
        $o = Options::parse($attrs, $settings['display']);
        $query = Catalog::sanitizeQuery(Options::queryFor($o), Catalog::maxLimit($settings['apiKey'] !== ''));
        $out = $this->lookup($query);

        return [
            'cards' => View::buildCards($out['products'], $o, self::locale()),
            'products' => $out['products'],
            'ref' => $out['ref'],
            'viewAll' => $o['viewAll'] ? View::viewAllHref($query, $out['products'], $out['ref']) : null,
            'disclosure' => LibSettings::disclosureFor($settings, $out['ref']),
        ];
    }

    /** Load the stylesheet and carousel script once per page (unless turned off). */
    public function registerAssets(): void
    {
        if (!Plugin::getInstance()->getSettings()->includeAssets) {
            return;
        }
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return;
        }
        Craft::$app->getView()->registerAssetBundle(EmbedAsset::class);
    }

    /** "Refresh products now": drop cached products and the key check. */
    public function refresh(): void
    {
        $this->client()->flush();
        $this->memo = [];
    }

    /** Everything the settings page status line shows. Never contains the secret key. */
    public function status(): array
    {
        $settings = $this->settings();
        $client = $this->client();

        return [
            'hasKey' => $settings['rawApiKey'] !== '',
            'keyProblem' => $settings['keyProblem'],
            'key' => $client->keyInfo(),
            'ref' => $client->ref() ?: $settings['refCode'],
            'stats' => $client->stats(),
        ];
    }
}
