<?php

namespace pickhero\commerce\controllers\admin;

use Craft;
use craft\commerce\Plugin as CommercePlugin;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use pickhero\commerce\CommercePickheroPlugin;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Admin controller for manual order synchronization actions
 */
class OrdersController extends Controller
{
    /**
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-pickhero');
    }

    /**
     * Submit an order to PickHero
     * 
     * @throws NotFoundHttpException
     * @throws MissingComponentException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionPush(): Response
    {
        $this->requirePermission('commerce-pickhero-pushOrders');
        
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = CommercePlugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order || !$order->isCompleted) {
            throw new NotFoundHttpException();
        }

        $success = false;
        try {
            $syncStatus = CommercePickheroPlugin::getInstance()->orderSync->getSyncStatus($order);
            $success = CommercePickheroPlugin::getInstance()->orderSync->submitToPickHero($syncStatus, true);
        } catch (\Exception $e) {
            CommercePickheroPlugin::getInstance()->log->error("Failed to submit order to PickHero.", $e);
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(
                Craft::t('commerce-pickhero', "Order sent to PickHero successfully.")
            );
        } else {
            Craft::$app->getSession()->setError(
                Craft::t('commerce-pickhero', "Failed to send order to PickHero. Check storage/logs/pickhero.log for details.")
            );
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Unlink an order from PickHero (clears local reference only)
     * 
     * @throws NotFoundHttpException
     * @throws MissingComponentException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionUnlink(): Response
    {
        $this->requirePermission('commerce-pickhero-pushOrders');
        
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = CommercePlugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order || !$order->isCompleted) {
            throw new NotFoundHttpException();
        }

        try {
            $syncStatus = CommercePickheroPlugin::getInstance()->orderSync->getSyncStatus($order);
            
            // Increment submission count for unique external_id
            $syncStatus->submissionCount++;
            
            // Clear existing PickHero order data
            $syncStatus->pickheroOrderId = null;
            $syncStatus->pickheroOrderNumber = null;
            $syncStatus->pushed = false;
            $syncStatus->stockAllocated = false;
            $syncStatus->processed = false;
            $syncStatus->publicStatusPage = null;
            
            CommercePickheroPlugin::getInstance()->orderSync->saveSyncStatus($syncStatus);
            
            Craft::$app->getSession()->setNotice(
                Craft::t('commerce-pickhero', "Order unlinked from PickHero.")
            );
        } catch (\Exception $e) {
            CommercePickheroPlugin::getInstance()->log->error("Failed to unlink order from PickHero.", $e);
            Craft::$app->getSession()->setError(
                Craft::t('commerce-pickhero', "Failed to unlink order from PickHero.")
            );
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Trigger order processing in PickHero
     * 
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     * @throws MissingComponentException
     * @throws NotFoundHttpException
     */
    public function actionProcess(): Response
    {
        $this->requirePermission('commerce-pickhero-pushOrders');
        
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = CommercePlugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order || !$order->isCompleted) {
            throw new NotFoundHttpException();
        }

        $success = false;
        try {
            $syncStatus = CommercePickheroPlugin::getInstance()->orderSync->getSyncStatus($order);
            $success = CommercePickheroPlugin::getInstance()->orderSync->triggerProcessing($syncStatus);
        } catch (\Exception $e) {
            CommercePickheroPlugin::getInstance()->log->error("Failed to process order in PickHero.", $e);
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(
                Craft::t('commerce-pickhero', "Order processing triggered successfully.")
            );
        } else {
            Craft::$app->getSession()->setError(
                Craft::t('commerce-pickhero', "Failed to process order in PickHero. Check the logs for details.")
            );
        }

        return $this->redirectToPostedUrl();
    }
}
