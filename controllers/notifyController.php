<?php

use Pagantis\ModuleUtils\Exception\OrderNotCreatedException;
use Pagantis\OrdersApiClient\Client;
use Pagantis\ModuleUtils\Exception\ConcurrencyException;
use Pagantis\ModuleUtils\Exception\AlreadyProcessedException;
use Pagantis\ModuleUtils\Exception\AmountMismatchException;
use Pagantis\ModuleUtils\Exception\MerchantOrderNotFoundException;
use Pagantis\ModuleUtils\Exception\NoIdentificationException;
use Pagantis\ModuleUtils\Exception\OrderNotFoundException;
use Pagantis\ModuleUtils\Exception\QuoteNotFoundException;
use Pagantis\ModuleUtils\Exception\UnknownException;
use Pagantis\ModuleUtils\Exception\WrongStatusException;
use Pagantis\ModuleUtils\Model\Response\JsonSuccessResponse;
use Pagantis\ModuleUtils\Model\Response\JsonExceptionResponse;
use Pagantis\ModuleUtils\Model\Log\LogEntry;
use Pagantis\OrdersApiClient\Model\Order;

if ( ! defined('ABSPATH')) {
    exit;
}

class WcPagantisNotify extends WcPagantisGateway
{
    /** Concurrency table name  */
    const CONCURRENCY_TABLE = 'pagantis_concurrency';

    /** Seconds to expire a locked request */
    const CONCURRENCY_TIMEOUT = 5;

    /** @var mixed $pagantisOrder */
    protected $pagantisOrder;

    /** @var $string $origin */
    public $origin;

    /** @var $string */
    public $order;

    /** @var mixed $woocommerceOrderId */
    protected $woocommerceOrderId = '';

    /** @var mixed $cfg */
    protected $cfg;

    /** @var Client $orderClient */
    protected $orderClient;

    /** @var  WC_Order $woocommerceOrder */
    protected $woocommerceOrder;

    /** @var mixed $pagantisOrderId */
    protected $pagantisOrderId = '';

    /** @var $string */
    protected $pagantisOrderStatus;

    /**
     * Validation vs PagantisClient
     *
     * @return JsonExceptionResponse|JsonSuccessResponse
     * @throws ConcurrencyException
     */
    public function processInformation()
    {
        try {
            require_once(__ROOT__ . '/vendor/autoload.php');
            try {
                if ($_SERVER['REQUEST_METHOD'] == 'GET' && $_GET['origin'] == 'notification') {
                    return $this->buildResponse();
                }
                $this->checkConcurrency();
                $this->getMerchantOrder();
                $this->getPgOrderId();
                $this->getPgOrder();
                $isPagantisOrderProcessed = $this->checkPgOrderStatus();
                if ($isPagantisOrderProcessed) {
                    return $this->buildResponse();
                }
                $this->validateAmount();
                if ($this->checkMerchantOrderStatus()) {
                    $this->processMerchantOrder();
                }
            } catch (\Exception $exception) {
                $this->insertLog($exception);

                return $this->buildResponse($exception);
            }

            try {
                $this->confirmPgOrder();

                return $this->buildResponse();
            } catch (\Exception $exception) {
                $this->rollbackMerchantOrder();
                $this->insertLog($exception);

                return $this->buildResponse($exception);
            }
        } catch (\Exception $exception) {
            $this->insertLog($exception);

            return $this->buildResponse($exception);
        }
    }

    /**
     * COMMON FUNCTIONS
     */

    /**
     * @throws ConcurrencyException
     * @throws QuoteNotFoundException
     */
    private function checkConcurrency()
    {
        $this->woocommerceOrderId = $_GET['order-received'];
        if ($this->woocommerceOrderId == '') {
            throw new QuoteNotFoundException();
        }

        $this->unblockConcurrency();
        $this->blockConcurrency($this->woocommerceOrderId);
    }

    /**
     * @throws MerchantOrderNotFoundException
     */
    private function getMerchantOrder()
    {
        try {
            $this->woocommerceOrder = new WC_Order($this->woocommerceOrderId);
            $this->woocommerceOrder->set_payment_method_title(Ucfirst(WcPagantisGateway::METHOD_ID));
        } catch (\Exception $e) {
            throw new MerchantOrderNotFoundException();
        }
    }

    /**
     * @throws NoIdentificationException
     */
    private function getPgOrderId()
    {
        global $wpdb;
        $this->checkDbTable();
        $tableName             = $wpdb->prefix . self::ORDERS_TABLE;
        $queryResult           = $wpdb->get_row("select order_id from $tableName where id='" . $this->woocommerceOrderId . "'");
        $this->pagantisOrderId = $queryResult->order_id;

        if ($this->pagantisOrderId == '') {
            throw new NoIdentificationException();
        }
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getPgOrder()
    {
        try {
            $this->cfg           = get_option('woocommerce_pagantis_settings');
            $this->orderClient   = new Client($this->cfg['pagantis_public_key'], $this->cfg['pagantis_private_key']);
            $this->pagantisOrder = $this->orderClient->getOrder($this->pagantisOrderId);
        } catch (\Exception $e) {
            throw new OrderNotFoundException();
        }
    }


    /**
     * @return bool
     * @throws WrongStatusException
     */
    private function checkPgOrderStatus()
    {
        try {
            $this->checkPagantisDeepStatus(array('AUTHORIZED'));
        } catch (\Exception $e) {
            if ($this->pagantisOrder instanceof Order) {
                $status = $this->pagantisOrder->getStatus();
            } else {
                $status = '-';
            }

            if ($status === Order::STATUS_CONFIRMED) {
                return true;
            }
            throw new WrongStatusException($status);
        }
    }

    /**
     * @return void
     * @throws OrderNotCreatedException
     * @throws UnknownException
     */
    private function deleteMerchantOrder()
    {
        if ( ! $this->woocommerceOrder instanceof WC_Order) {
            throw new UnknownException ('Order :' . $this->woocommerceOrder->get_id() . "couldn't be created");
        }
        try {
            $this->woocommerceOrder->delete($force_delete = true);
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }
    }

    /**
     * @return bool
     */
    private function checkMerchantOrderStatus()
    {
        //Order status reference => https://docs.woocommerce.com/document/managing-orders/
        $validOrderStatuses = array('on-hold', 'pending', 'failed', 'processing', 'completed');
        $isOrderStatusValid = apply_filters('woocommerce_valid_order_statuses_for_payment_complete', $validOrderStatuses, $this);
        $this->woocommerceOrder->save();
        if ( ! $this->woocommerceOrder->has_status($isOrderStatusValid)) { // TO CONFIRM
            $logMessage = "WARNING checkMerchantOrderStatus." . " Merchant order id:" . $this->woocommerceOrder->get_id() . " Merchant order status:"
                          . $this->woocommerceOrder->get_status() . " Pagantis order id:" . $this->pagantisOrder->getStatus()
                          . " Pagantis order status:" . $this->pagantisOrder->getId();

            $this->insertLog(null, $logMessage);
            $this->woocommerceOrder->add_order_note($logMessage);

            return false;
        } else {
            return true; //TO SAVE
            // if ($this->woocommerceOrder->needs_processing()) {
            // }
        }
    }

    /**
     * @throws AmountMismatchException
     */
    private function validateAmount()
    {
        $pagantisAmount = $this->pagantisOrder->getShoppingCart()->getTotalAmount();
        $wcAmount       = intval(strval(100 * $this->woocommerceOrder->get_total()));
        if ($pagantisAmount != $wcAmount) {
            throw new AmountMismatchException($pagantisAmount, $wcAmount);
        }
    }

    /**
     * @throws Exception
     */
    private function processMerchantOrder()
    {
        $this->saveMerchantOrder();
        $this->updateDbInfo();
    }

    /**
     * @return false|string
     * @throws UnknownException
     */
    private function confirmPgOrder()
    {
        try {
            $this->pagantisOrder = $this->orderClient->confirmOrder($this->pagantisOrderId);
        } catch (\Exception $e) {
            $this->pagantisOrder = $this->orderClient->getOrder($this->pagantisOrderId);
            if ($this->pagantisOrder->getStatus() !== Order::STATUS_CONFIRMED) {
                throw new UnknownException($e->getMessage());
            } else {
                $logMessage = 'Concurrency issue: Order_id ' . $this->pagantisOrderId . ' was confirmed by other process';
                $this->insertLog(null, $logMessage);
            }
        }

        $jsonResponse = new JsonSuccessResponse();

        return $jsonResponse->toJson();
    }

    /**
     * UTILS FUNCTIONS
     */
    /** STEP 1 CC - Check concurrency */
    /**
     * Check if orders table exists
     */
    private function checkDbTable()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . self::ORDERS_TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE $tableName (id int, order_id varchar(50), wc_order_id varchar(50), 
                  UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Check if logs table exists
     */
    private function checkDbLogTable()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . self::LOGS_TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE $tableName ( id int NOT NULL AUTO_INCREMENT, log text NOT NULL, 
                    createdAt timestamp DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        return;
    }

    /** STEP 2 GMO - Get Merchant Order */
    /** STEP 3 GPOI - Get Pagantis OrderId */
    /** STEP 4 GPO - Get Pagantis Order */
    /** STEP 5 COS - Check Order Status */

    /**
     * @param $statusArray
     *
     * @throws \Exception
     */
    private function checkPagantisDeepStatus($statusArray)
    {
        $pagantisStatus = array();
        foreach ($statusArray as $status) {
            $pagantisStatus[] = constant("\Pagantis\OrdersApiClient\Model\Order::STATUS_$status");
        }

        if ($this->pagantisOrder instanceof Order) {
            $paid = in_array($this->pagantisOrder->getStatus(), $pagantisStatus);
            if ( ! $paid) {
                if ($this->pagantisOrder instanceof Order) {
                    $status = $this->pagantisOrder->getStatus();
                } else {
                    $status = '-';
                }
                throw new WrongStatusException($status);
            }
        } else {
            throw new OrderNotFoundException();
        }
    }

    /**
     * @return string
     * @throws OrderNotFoundException
     */
    private function updatePgOrderStatus()
    {
        if ( ! $this->pagantisOrder instanceof Order) {
            throw new OrderNotFoundException();
        }
        $orderStatus = $this->pagantisOrder->getStatus();
        if ( ! $this->isPgOrderAuthorized($orderStatus)) {
            return $this->setPgOrderStatus($orderStatus);
        }

        return $this->setPgOrderStatus('-');
    }

    /** STEP 6 CMOS - Check Merchant Order Status */
    /** STEP 7 VA - Validate Amount */
    /** STEP 8 PMO - Process Merchant Order */
    /**
     * @throws \Exception
     */
    private function saveMerchantOrder()
    {
        global $woocommerce;
        $paymentResult = $this->woocommerceOrder->payment_complete();
        if ($paymentResult) {
            $this->woocommerceOrder->add_order_note("Payment Complete Continuing");

            $metadataOrder = $this->pagantisOrder->getMetadata();
            $metadataInfo  = null;
            foreach ($metadataOrder as $metadataKey => $metadataValue) {
                if ($metadataKey == 'promotedProduct') {
                    $metadataInfo .= "/Producto promocionado = $metadataValue";
                }
            }

            if ($metadataInfo != null) {
                $this->woocommerceOrder->add_order_note($metadataInfo);
            }

            $this->woocommerceOrder->add_order_note("Notification received via $this->origin");
            $this->woocommerceOrder->add_order_note("Order Payment Completed via $this->origin");

            // https://docs.woocommerce.com/wc-apidocs/source-function-wc_reduce_stock_levels.html#147
            //$this->woocommerceOrder->reduce_order_stock();
            wc_reduce_stock_levels($this->woocommerceOrderId);
            $this->woocommerceOrder->save();


            //$woocommerce->cart->empty_cart();
            sleep(3);
        } else {
            throw new UnknownException('Order can not be saved');
        }
    }

    /**
     * Save the merchant order_id with the related identification
     */
    private function updateDbInfo()
    {
        global $wpdb;

        $this->checkDbTable();
        $tableName = $wpdb->prefix . self::ORDERS_TABLE;

        $wpdb->update($tableName, array('wc_order_id' => $this->woocommerceOrderId), array('id' => $this->woocommerceOrderId), array('%s'),
            array('%d'));
    }

    /** STEP 9 CPO - Confirmation Pagantis Order */
    private function rollbackMerchantOrder()
    {
        $this->woocommerceOrder->update_status('pending', __('Pending payment', 'woocommerce'));
    }

    /**
     * @param null $exception
     * @param null $message
     */
    private function insertLog($exception = null, $message = null)
    {
        global $wpdb;

        $this->checkDbLogTable();
        $logEntry = new LogEntry();
        if ($exception instanceof \Exception) {
            $logEntry = $logEntry->error($exception);
        } else {
            $logEntry = $logEntry->info($message);
        }

        $tableName = $wpdb->prefix . self::LOGS_TABLE;
        $wpdb->insert($tableName, array('log' => $logEntry->toJson()));
    }

    /**
     * @param null $orderId
     *
     * @throws ConcurrencyException
     */
    private function unblockConcurrency($orderId = null)
    {
        global $wpdb;
        $tableName = $wpdb->prefix . self::CONCURRENCY_TABLE;
        if ($orderId == null) {
            $query = "DELETE FROM $tableName WHERE createdAt<(NOW()- INTERVAL " . self::CONCURRENCY_TIMEOUT . " SECOND)";
        } else {
            $query = "DELETE FROM $tableName WHERE order_id = $orderId";
        }
        $resultDelete = $wpdb->query($query);
        if ($resultDelete === false) {
            throw new ConcurrencyException();
        }
    }

    /**
     * @param $orderId
     *
     * @throws ConcurrencyException
     */
    private function blockConcurrency($orderId)
    {
        global $wpdb;
        $tableName    = $wpdb->prefix . self::CONCURRENCY_TABLE;
        $insertResult = $wpdb->insert($tableName, array('order_id' => $orderId));
        if ($insertResult === false) {
            if ($this->getOrigin() == 'Notify') {
                throw new ConcurrencyException();
            } else {
                $query           =
                    sprintf("SELECT TIMESTAMPDIFF(SECOND,NOW()-INTERVAL %s SECOND, createdAt) as rest FROM %s WHERE %s", self::CONCURRENCY_TIMEOUT,
                        $tableName, "order_id=$orderId");
                $resultSeconds   = $wpdb->get_row($query);
                $restSeconds     = isset($resultSeconds) ? ($resultSeconds->rest) : 0;
                $secondsToExpire = ($restSeconds > self::CONCURRENCY_TIMEOUT) ? self::CONCURRENCY_TIMEOUT : $restSeconds;
                sleep($secondsToExpire + 1);

                $logMessage =
                    sprintf("User waiting %s seconds, default seconds %s, bd time to expire %s seconds", $secondsToExpire, self::CONCURRENCY_TIMEOUT,
                        $restSeconds);
                $this->insertLog(null, $logMessage);
            }
        }
    }

    /**
     * @param null $exception
     *
     * @return JsonExceptionResponse|JsonSuccessResponse
     * @throws ConcurrencyException
     */
    private function buildResponse($exception = null)
    {
        $this->unblockConcurrency($this->woocommerceOrderId);

        if ($exception == null) {
            $jsonResponse = new JsonSuccessResponse();
        } else {
            $jsonResponse = new JsonExceptionResponse();
            $jsonResponse->setException($exception);
        }

        $jsonResponse->setMerchantOrderId($this->woocommerceOrderId);
        $jsonResponse->setPagantisOrderId($this->pagantisOrderId);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $jsonResponse->printResponse();
        } else {
            return $jsonResponse;
        }
    }

    /**
     * @param string $currentOrderStatus
     *
     * @return bool
     */
    private function isPgOrderAuthorized($currentOrderStatus)
    {
        if ( ! $currentOrderStatus == Order::STATUS_CONFIRMED) {
            return false;
        }

        return true;
    }


    /**
     * @param mixed $status
     *
     * @return string
     */
    protected function setPgOrderStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    protected function getPgOrderStatus()
    {
        return $this->status;
    }

    /**
     * GETTERS & SETTERS
     */

    /**
     * @return mixed
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * @param mixed $origin
     */
    public function setOrigin($origin)
    {
        $this->origin = $origin;
    }
}
