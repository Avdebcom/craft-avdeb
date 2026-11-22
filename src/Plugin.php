<?php

namespace avdeb\craftavdeb;

use avdeb\craftavdeb\models\Settings;
use avdeb\craftavdeb\services\Embeds;
use avdeb\craftavdeb\twig\Extension;
use avdeb\craftavdeb\variables\AvdebVariable;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterCacheOptionsEvent;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

/**
 * AVDEB Products: product embeds with your affiliate referral code.
 *
 * - Twig: `{{ avdebProducts({ url: 'https://avdeb.com/category/stickers/' }) }}`, `avdebProduct()`,
 *   `avdebButton()`, `craft.avdeb.*`, and the `|avdeb` filter for `{avdeb-products …}` tags in rich text.
 * - Settings: Settings → Plugins → AVDEB Products (or `config/avdeb.php`).
 *
 * @property-read Embeds $embeds
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'embeds' => Embeds::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->getView()->registerTwigExtension(new Extension());

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function (Event $event): void {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('avdeb', AvdebVariable::class);
        });

        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS, function (RegisterCacheOptionsEvent $event): void {
            $event->options[] = [
                'key' => 'avdeb-products',
                'label' => Craft::t('avdeb', 'AVDEB product data'),
                'action' => fn() => $this->embeds->refresh(),
            ];
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('avdeb/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'overrides' => array_keys(Craft::$app->getConfig()->getConfigFromFile($this->handle)),
        ]);
    }

    /**
     * Saved settings change what embeds show: start from a fresh client.
     */
    public function afterSaveSettings(): void
    {
        parent::afterSaveSettings();
        $this->embeds->reset();
    }
}
