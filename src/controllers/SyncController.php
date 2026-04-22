<?php

namespace splendidweb\googlereviews\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use splendidweb\googlereviews\Plugin;
use yii\web\Response;

class SyncController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    /**
     * Runs a manual sync from the CP settings screen.
     */
    public function actionRun(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('accessCp');

        $result = Plugin::getInstance()->sync->sync();
        $session = Craft::$app->getSession();

        if ($result->hasSyncErrors()) {
            $session->setError('Sync failed: ' . implode(' | ', $result->errors));
        } else {
            $session->setNotice(sprintf(
                'Sync complete. Fetched: %d, Upserted: %d, Skipped: %d, Archived: %d.',
                $result->fetched,
                $result->upserted,
                $result->skipped,
                $result->archived
            ));
        }

        return $this->redirect(UrlHelper::cpUrl('settings/plugins/google-reviews'));
    }
}
