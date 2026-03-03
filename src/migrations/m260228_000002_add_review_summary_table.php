<?php

namespace splendidweb\googlereviews\migrations;

use craft\db\Migration;

class m260228_000002_add_review_summary_table extends Migration
{
    public function safeUp(): bool
    {
        $summaryTable = '{{%googlereviews_summary}}';
        if ($this->db->tableExists($summaryTable)) {
            return true;
        }

        $this->createTable($summaryTable, [
            'id' => $this->primaryKey(),
            'sourceMode' => $this->string(50)->notNull()->defaultValue('mock'),
            'overallRating' => $this->decimal(3, 2),
            'totalReviewCount' => $this->integer(),
            'googlePlaceId' => $this->string(255),
            'lastSyncedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        $summaryTable = '{{%googlereviews_summary}}';
        if ($this->db->tableExists($summaryTable)) {
            $this->dropTable($summaryTable);
        }

        return true;
    }
}
