<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\commerce\records\OrderHistory as OrderHistoryRecord;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * Order history service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class OrderHistories extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event TransactionEvent The event that is triggered when order status is changed
     */
    const EVENT_ORDER_STATUS_CHANGE = 'orderStatusChange';

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
     * @return OrderHistory|null
     */
    public function getOrderHistoryById($id)
    {
        $result = OrderHistoryRecord::findOne($id);

        if ($result) {
            return new OrderHistory($result);
        }

        return null;
    }

    /**
     * @param int $id orderId
     *
     * @return OrderHistory[]
     */
    public function getAllOrderHistoriesByOrderId($id): array
    {
        $records = OrderHistoryRecord::find()->where(['orderId' => $id])->orderBy('dateCreated')->all();

        return ArrayHelper::map($records, 'id', function($record) {
            return $this->_createOrderHistoryFromOrderHistoryRecord($record);
        });
    }

    /**
     * @param Order $order
     * @param int   $oldStatusId
     *
     * @return bool
     * @throws Exception
     */
    public function createOrderHistoryFromOrder(Order $order, int $oldStatusId): bool
    {
        $orderHistoryModel = new OrderHistory();
        $orderHistoryModel->orderId = $order->id;
        $orderHistoryModel->prevStatusId = $oldStatusId;
        $orderHistoryModel->newStatusId = $order->orderStatusId;
        $orderHistoryModel->customerId = Craft::$app->request->isConsoleRequest ? $order->customerId : Plugin::getInstance()->getCustomers()->getCustomerId();
        $orderHistoryModel->message = $order->message;

        if (!$this->saveOrderHistory($orderHistoryModel)) {
            return false;
        }

        Plugin::getInstance()->getOrderStatuses()->statusChangeHandler($order, $orderHistoryModel);

        //raising event on status change
        $event = new OrderStatusEvent([
            'orderHistory' => $orderHistoryModel,
            'order' => $order
        ]);
        $this->trigger(self::EVENT_ORDER_STATUS_CHANGE, $event);

        return true;
    }

    /**
     * @param OrderHistory $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveOrderHistory(OrderHistory $model): bool
    {
        if ($model->id) {
            $record = OrderHistoryRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No order history exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new OrderHistoryRecord();
        }

        $record->message = $model->message;
        $record->newStatusId = $model->newStatusId;
        $record->prevStatusId = $model->prevStatusId;
        $record->customerId = $model->customerId;
        $record->orderId = $model->orderId;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;
            $model->dateCreated = $record->dateCreated;

            return true;
        }
        return false;
    }

    /**
     * @param $id
     *
     * @return bool|int
     */
    public function deleteOrderHistoryById($id)
    {
        $orderHistory = OrderHistoryRecord::findOne($id);

        if ($orderHistory) {
            return $orderHistory->delete();
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * @param OrderHistoryRecord $record
     *
     * @return OrderHistory
     */
    private function _createOrderHistoryFromOrderHistoryRecord(OrderHistoryRecord $record): OrderHistory
    {
        return new OrderHistory($record->toArray([
            'id',
            'message',
            'orderId',
            'prevStatusId',
            'newStatusId',
            'customerId'
        ]));
    }
}
