<?php

namespace splendidweb\googlereviews\console\controllers;

use craft\console\Controller;
use splendidweb\googlereviews\Plugin;
use yii\console\ExitCode;

class SyncController extends Controller
{
    public function actionIndex(): int
    {
        $result = Plugin::getInstance()->sync->sync();

        $this->stdout(sprintf("Fetched: %d\n", $result->fetched));
        $this->stdout(sprintf("Upserted: %d\n", $result->upserted));
        $this->stdout(sprintf("Skipped: %d\n", $result->skipped));
        $this->stdout(sprintf("Archived: %d\n", $result->archived));

        if ($result->hasSyncErrors()) {
            foreach ($result->errors as $error) {
                $this->stderr("Error: {$error}\n");
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
