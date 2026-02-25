<?php

namespace splendidweb\googlereviews;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\twig\variables\CraftVariable;
use splendidweb\googlereviews\models\Settings;
use splendidweb\googlereviews\services\SyncService;
use splendidweb\googlereviews\variables\GoogleReviewsVariable;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public static Plugin $plugin;

    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->setComponents([
            'sync' => SyncService::class,
        ]);

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('googleReviews', GoogleReviewsVariable::class);
            }
        );

        Craft::info(
            'Google Reviews plugin loaded.',
            __METHOD__
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'google-reviews/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }
}
