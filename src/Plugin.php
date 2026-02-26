<?php

namespace splendidweb\googlereviews;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Elements;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use splendidweb\googlereviews\elements\GoogleReview;
use splendidweb\googlereviews\models\Settings;
use splendidweb\googlereviews\services\SyncService;
use splendidweb\googlereviews\variables\GoogleReviewsVariable;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public static Plugin $plugin;

    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;
    public string $schemaVersion = '1.0.1';

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

        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = GoogleReview::class;
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function(RegisterUrlRulesEvent $event): void {
                $event->rules['google-reviews'] = ['template' => 'google-reviews/reviews/_index'];
                $event->rules['google-reviews/reviews'] = ['template' => 'google-reviews/reviews/_index'];
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

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        if ($item === null) {
            return null;
        }

        $item['subnav'] = [
            'reviews' => [
                'label' => 'Reviews',
                'url' => 'google-reviews/reviews',
            ],
            'settings' => [
                'label' => 'Settings',
                'url' => 'settings/plugins/google-reviews',
            ],
        ];

        return $item;
    }
}
