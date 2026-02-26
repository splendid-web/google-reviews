<?php

namespace splendidweb\googlereviews\migrations;

use craft\db\Migration;

class m260225_000001_add_reply_fields extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%googlereviews_reviews}}';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null) {
            return true;
        }

        if (!isset($schema->columns['replyText'])) {
            $this->addColumn($table, 'replyText', $this->text()->after('reviewDate'));
        }

        if (!isset($schema->columns['replyUpdatedAt'])) {
            $this->addColumn($table, 'replyUpdatedAt', $this->dateTime()->after('replyText'));
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

        if (isset($schema->columns['replyUpdatedAt'])) {
            $this->dropColumn($table, 'replyUpdatedAt');
        }

        if (isset($schema->columns['replyText'])) {
            $this->dropColumn($table, 'replyText');
        }

        return true;
    }
}
