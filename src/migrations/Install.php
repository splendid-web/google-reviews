<?php

namespace splendidweb\googlereviews\migrations;

use Craft;
use craft\db\Migration;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();

        if ($entries->getSectionByHandle('googleReviews') !== null) {
            return true;
        }

        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;

        $section = new Section([
            'name' => 'Google Reviews',
            'handle' => 'googleReviews',
            'type' => Section::TYPE_CHANNEL,
            'enableVersioning' => false,
        ]);

        $section->setSiteSettings([
            new Section_SiteSettings([
                'siteId' => $primarySiteId,
                'enabledByDefault' => true,
                'hasUrls' => false,
                'uriFormat' => null,
                'template' => null,
            ]),
        ]);

        $section->setEntryTypes([
            new EntryType([
                'name' => 'Google Review',
                'handle' => 'googleReview',
                'hasTitleField' => true,
                'titleLabel' => 'Review Title',
            ]),
        ]);

        return $entries->saveSection($section);
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();
        $section = $entries->getSectionByHandle('googleReviews');

        if ($section === null) {
            return true;
        }

        return $entries->deleteSectionById($section->id);
    }
}
