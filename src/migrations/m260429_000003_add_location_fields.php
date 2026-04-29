<?php

namespace splendidweb\googlereviews\migrations;

use craft\db\Migration;
use Throwable;

class m260429_000003_add_location_fields extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%googlereviews_reviews}}';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null) {
            return true;
        }

        if (!isset($schema->columns['sourceLocationId'])) {
            $this->addColumn($table, 'sourceLocationId', $this->string(255)->notNull()->defaultValue('')->after('reviewUrl'));
        }

        if (!isset($schema->columns['sourceLocationName'])) {
            $this->addColumn($table, 'sourceLocationName', $this->string(255)->notNull()->defaultValue('')->after('sourceLocationId'));
        }

        $schema = $this->db->getTableSchema($table, true);
        if ($schema !== null && isset($schema->columns['sourceLocationId'])) {
            try {
                $this->createIndex('idx_googlereviews_reviews_sourceLocationId', $table, ['sourceLocationId'], false);
            } catch (Throwable $exception) {
                // Ignore duplicate-index style errors on re-run; migration must stay idempotent.
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%googlereviews_reviews}}';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null) {
            return true;
        }

        if (isset($schema->columns['sourceLocationName'])) {
            $this->dropColumn($table, 'sourceLocationName');
        }

        if (isset($schema->columns['sourceLocationId'])) {
            $this->dropColumn($table, 'sourceLocationId');
        }

        return true;
    }

}
