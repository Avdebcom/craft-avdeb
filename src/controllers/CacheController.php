<?php

namespace avdeb\craftavdeb\controllers;

use avdeb\craftavdeb\Plugin;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * "Refresh products now" on the settings page.
 */
class CacheController extends Controller
{
    public function actionRefresh(): Response
    {
        $this->requirePostRequest();
        $this->requireAdmin(false);

        Plugin::getInstance()->embeds->refresh();

        return $this->asSuccess(Craft::t('avdeb', 'Cached AVDEB products were cleared. Embeds fetch fresh data on their next view.'));
    }
}
