<?php

namespace splendidweb\googlereviews\models;

use craft\base\Model;

class SyncResult extends Model
{
    public int $fetched = 0;
    public int $upserted = 0;
    public int $skipped = 0;
    public int $archived = 0;

    /** @var string[] */
    public array $errors = [];

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
