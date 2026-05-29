<?php

namespace splendidweb\googlereviews\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

class m260529_000005_cleanup_legacy_review_rows extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%googlereviews_reviews}}';
        if (!$this->db->tableExists($table)) {
            return true;
        }

        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns['sourceLocationId'])) {
            return true;
        }

        $ids = (new Query())
            ->select(['id'])
            ->from($table)
            ->where(['sourceLocationId' => ''])
            ->andWhere(['not like', 'googleReviewId', 'mock-%', false])
            ->column();

        $elementsService = Craft::$app->getElements();
        foreach ($ids as $id) {
            $elementsService->deleteElementById((int)$id);
        }

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
