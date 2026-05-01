<?php

namespace splendidweb\googlereviews\migrations;

use craft\db\Migration;
use Throwable;

class m260501_000004_add_summary_location_fields extends Migration
{
    public function safeUp(): bool
    {
        $summaryTable = '{{%googlereviews_summary}}';
        $schema = $this->db->getTableSchema($summaryTable, true);
        if ($schema === null) {
            return true;
        }

        if (!isset($schema->columns['sourceLocationId'])) {
            $this->addColumn($summaryTable, 'sourceLocationId', $this->string(255)->notNull()->defaultValue('')->after('id'));
        }

        if (!isset($schema->columns['sourceLocationName'])) {
            $this->addColumn($summaryTable, 'sourceLocationName', $this->string(255)->notNull()->defaultValue('')->after('sourceLocationId'));
        }

        $this->update($summaryTable, ['sourceLocationId' => ''], ['sourceLocationId' => null], [], false);
        $this->update($summaryTable, ['sourceLocationName' => ''], ['sourceLocationName' => null], [], false);
        $this->update($summaryTable, ['sourceLocationName' => 'All Locations'], ['sourceLocationId' => '', 'sourceLocationName' => ''], [], false);

        try {
            $this->createIndex('idx_googlereviews_summary_sourceLocationId_unique', $summaryTable, ['sourceLocationId'], true);
        } catch (Throwable) {
            // Ignore duplicate-index errors so migration remains idempotent.
        }

        return true;
    }

    public function safeDown(): bool
    {
        $summaryTable = '{{%googlereviews_summary}}';
        $schema = $this->db->getTableSchema($summaryTable, true);
        if ($schema === null) {
            return true;
        }

        try {
            $this->dropIndex('idx_googlereviews_summary_sourceLocationId_unique', $summaryTable);
        } catch (Throwable) {
            // Ignore if index does not exist.
        }

        if (isset($schema->columns['sourceLocationName'])) {
            $this->dropColumn($summaryTable, 'sourceLocationName');
        }

        if (isset($schema->columns['sourceLocationId'])) {
            $this->dropColumn($summaryTable, 'sourceLocationId');
        }

        return true;
    }
}
