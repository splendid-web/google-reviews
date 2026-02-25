<?php

namespace splendidweb\googlereviews\migrations;

use craft\db\Migration;
use craft\db\Table;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%googlereviews_reviews}}';

        if ($this->db->tableExists($table)) {
            return true;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'googleReviewId' => $this->string(255)->notNull(),
            'authorName' => $this->string(255)->notNull()->defaultValue(''),
            'authorPhotoUrl' => $this->string(1024)->notNull()->defaultValue(''),
            'rating' => $this->smallInteger()->notNull()->defaultValue(0),
            'reviewText' => $this->text(),
            'reviewDate' => $this->dateTime(),
            'reviewUrl' => $this->string(1024)->notNull()->defaultValue(''),
            'source' => $this->string(50)->notNull()->defaultValue('Google'),
            'isImported' => $this->boolean()->notNull()->defaultValue(true),
            'featured' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $table, ['googleReviewId'], true);
        $this->createIndex(null, $table, ['rating']);
        $this->createIndex(null, $table, ['reviewDate']);
        $this->createIndex(null, $table, ['isImported']);
        $this->createIndex(null, $table, ['featured']);

        $this->addForeignKey(
            null,
            $table,
            'id',
            Table::ELEMENTS,
            'id',
            'CASCADE',
            null
        );

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%googlereviews_reviews}}';
        if ($this->db->tableExists($table)) {
            $this->dropTable($table);
        }

        return true;
    }
}
