<?php

namespace avdeb\craftavdeb\models;

use avdeb\craftavdeb\lib\Options;
use avdeb\craftavdeb\lib\Settings as LibSettings;
use craft\base\Model;
use craft\helpers\App;

/**
 * Plugin settings. Craft keeps them in project config, so the secret key should be an environment
 * variable (`$AVDEB_SECRET_KEY`); when the field is empty, AVDEB_SECRET_KEY and AVDEB_REF_CODE are
 * read from the environment anyway. Every value can also be set in `config/avdeb.php`.
 */
class Settings extends Model
{
    /** Secret key `avdeb_sk_…` or an environment variable such as `$AVDEB_SECRET_KEY`. */
    public string $apiKey = '';

    /** Referral code or an environment variable such as `$AVDEB_REF_CODE`. */
    public string $refCode = '';

    public string $layout = 'grid';
    public int $columns = 3;
    public string $cardStyle = 'elevated';
    public string $theme = 'site';
    public string $accentColor = '';
    public int $radius = 16;
    public string $imageRatio = 'square';
    public string $ctaLabel = 'View product';
    public bool $showPrice = true;
    public bool $showCreator = true;
    public bool $showBadges = true;
    public bool $newTab = false;
    public bool $showDisclosure = true;
    public string $disclosureText = LibSettings::DEFAULT_DISCLOSURE;
    public int $cacheMinutes = 360;

    /** Load the bundled stylesheet and carousel script. Turn off to bring your own CSS. */
    public bool $includeAssets = true;

    protected function defineRules(): array
    {
        return [
            [['apiKey', 'refCode', 'ctaLabel', 'disclosureText', 'accentColor'], 'trim'],
            ['refCode', 'match', 'pattern' => '/^(\$\w+|[A-Za-z0-9_-]{0,40})$/', 'message' => 'Use letters, numbers, "-" and "_" only (or an environment variable).'],
            ['apiKey', 'validateKey'],
            ['layout', 'in', 'range' => Options::LAYOUTS],
            ['cardStyle', 'in', 'range' => Options::CARD_STYLES],
            ['theme', 'in', 'range' => Options::THEMES],
            ['imageRatio', 'in', 'range' => Options::IMAGE_RATIOS],
            ['columns', 'integer', 'min' => 1, 'max' => 6],
            ['radius', 'integer', 'min' => 0, 'max' => 32],
            ['cacheMinutes', 'integer', 'min' => 5, 'max' => 2880],
            ['accentColor', 'match', 'pattern' => '/^(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}))?$/', 'message' => 'Use a hex colour such as #3b6548.'],
            ['ctaLabel', 'string', 'max' => 40],
            ['disclosureText', 'string', 'max' => 300],
            [['showPrice', 'showCreator', 'showBadges', 'newTab', 'showDisclosure', 'includeAssets'], 'boolean'],
        ];
    }

    public function validateKey(string $attribute): void
    {
        $value = $this->$attribute;
        if ($value === '' || str_starts_with($value, '$')) {
            return;
        }
        if (str_starts_with($value, 'avdeb_pk_')) {
            $this->addError($attribute, 'This is a publishable key (avdeb_pk_…). Use the secret key (avdeb_sk_…).');
        } elseif (!preg_match('/^avdeb_sk_[A-Za-z0-9]{32}$/', $value)) {
            $this->addError($attribute, 'Secret keys look like avdeb_sk_ followed by 32 letters and numbers.');
        }
    }

    /** The secret key with environment variables resolved ("" when unset). */
    public function resolvedApiKey(): string
    {
        return self::resolve($this->apiKey, 'AVDEB_SECRET_KEY');
    }

    /** The referral code with environment variables resolved ("" when unset). */
    public function resolvedRefCode(): string
    {
        return self::resolve($this->refCode, 'AVDEB_REF_CODE');
    }

    /** True when the key is typed in directly (it would end up in project config). */
    public function keyIsLiteral(): bool
    {
        return $this->apiKey !== '' && !str_starts_with($this->apiKey, '$');
    }

    private static function resolve(string $value, string $fallbackEnv): string
    {
        $resolved = $value !== '' ? App::parseEnv($value) : App::env($fallbackEnv);
        $resolved = is_string($resolved) ? trim($resolved) : '';

        return str_starts_with($resolved, '$') ? '' : $resolved;
    }

    /** Parameters for the shared library (Settings::fromParams / Options::display). */
    public function params(): array
    {
        return [
            'api_key' => $this->resolvedApiKey(),
            'ref_code' => $this->resolvedRefCode(),
            'layout' => $this->layout,
            'columns' => $this->columns,
            'card_style' => $this->cardStyle,
            'theme' => $this->theme,
            'accent_color' => $this->accentColor,
            'radius' => $this->radius,
            'image_ratio' => $this->imageRatio,
            'cta_label' => $this->ctaLabel,
            'show_price' => $this->showPrice,
            'show_creator' => $this->showCreator,
            'show_badges' => $this->showBadges,
            'new_tab' => $this->newTab,
            'show_disclosure' => $this->showDisclosure,
            'disclosure_text' => $this->disclosureText,
            'cache_minutes' => $this->cacheMinutes,
        ];
    }
}
