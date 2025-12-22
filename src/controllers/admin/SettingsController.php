<?php

namespace pickhero\commerce\controllers\admin;

use Craft;
use craft\web\Controller;
use pickhero\commerce\CommercePickheroPlugin;
use yii\web\Response;

/**
 * Settings page controller
 */
class SettingsController extends Controller
{
    public function init(): void
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-pickhero');
    }

    /**
     * Render the settings page
     */
    public function actionIndex(): Response
    {
        $settings = CommercePickheroPlugin::getInstance()->getSettings();
        $settings->validate();

        return $this->renderTemplate('commerce-pickhero/_settings', [
            'plugin' => CommercePickheroPlugin::getInstance(),
            'settings' => $settings,
            'allowAdminChanges' => Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }
}
