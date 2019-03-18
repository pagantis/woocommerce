<?php

use Pagantis\OrdersApiClient\Client;
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

if (!defined('ABSPATH')) {
    exit;
}

class WcPagantisNotify extends WcPagantisGateway
{
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

    /**
     * Validation vs PagantisClient
     *
     * @return array|Array_
     * @throws Exception
     */
    public function processInformation()
    {
        require_once(__ROOT__.'/vendor/autoload.php');
        try {
            $this->checkConcurrency();
            $this->getMerchantOrder();
            $this->getPagantisOrderId();
            $this->getPagantisOrder();
            $this->checkOrderStatus();
            $this->checkMerchantOrderStatus();
            $this->validateAmount();
            $this->processMerchantOrder();
        } catch (\Exception $exception) {
            $jsonResponse = new JsonExceptionResponse();
            $jsonResponse->setMerchantOrderId($this->woocommerceOrderId);
            $jsonResponse->setPagantisOrderId($this->pagantisOrderId);
            $jsonResponse->setException($exception);
            $response = $jsonResponse->toJson();
            $this->insertLog($exception);
        }
        try {
            if (!isset($response)) {
                $this->confirmPagantisOrder();
                $jsonResponse = new JsonSuccessResponse();
                $jsonResponse->setMerchantOrderId($this->woocommerceOrderId);
                $jsonResponse->setPagantisOrderId($this->pagantisOrderId);
            }
        } catch (\Exception $exception) {
            $this->rollbackMerchantOrder();
            $jsonResponse = new JsonExceptionResponse();
            $jsonResponse->setMerchantOrderId($this->woocommerceOrderId);
            $jsonResponse->setPagantisOrderId($this->pagantisOrderId);
            $jsonResponse->setException($exception);
            $jsonResponse->toJson();
            $this->insertLog($exception);
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $jsonResponse->printResponse();
        } else {
            return $jsonResponse;
        }
    }

    /**
     * COMMON FUNCTIONS
     */

    /**
     * @throws QuoteNotFoundException
     */
    private function checkConcurrency()
    {
        $this->woocommerceOrderId = $_GET['order-received'];
        if ($this->woocommerceOrderId == '') {
            throw new QuoteNotFoundException();
        }
    }

    /**
     * @throws MerchantOrderNotFoundException
     */
    private function getMerchantOrder()
    {
        try {
            $this->woocommerceOrder = new WC_Order($this->woocommerceOrderId);
        } catch (\Exception $e) {
            throw new MerchantOrderNotFoundException();
        }
    }

    /**
     * @throws NoIdentificationException
     */
    private function getPagantisOrderId()
    {
        global $wpdb;
        $this->checkDbTable();
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;
        $queryResult = $wpdb->get_row("select order_id from $tableName where id='".$this->woocommerceOrderId."'");
        $this->pagantisOrderId = $queryResult->order_id;

        if ($this->pagantisOrderId == '') {
            throw new NoIdentificationException();
        }
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getPagantisOrder()
    {
        try {
            $this->cfg = get_option('woocommerce_pagantis_settings');
            $this->orderClient = new Client($this->cfg['pagantis_public_key'], $this->cfg['pagantis_private_key']);
            $this->pagantisOrder = $this->orderClient->getOrder($this->pagantisOrderId);
        } catch (\Exception $e) {
            throw new OrderNotFoundException();
        }
    }

    /**
     * @throws AlreadyProcessedException
     * @throws WrongStatusException
     */
    private function checkOrderStatus()
    {
        try {
            $this->checkPagantisStatus(array('AUTHORIZED'));
        } catch (\Exception $e) {
            if ($this->woocommerceOrderId!='') {
                throw new AlreadyProcessedException();
            } else {
                if ($this->pagantisOrder instanceof \Pagantis\OrdersApiClient\Model\Order) {
                    $status = $this->pagantisOrder->getStatus();
                } else {
                    $status = '-';
                }
                throw new WrongStatusException($status);
            }
        }
    }

    /**
     * @throws AlreadyProcessedException
     */
    private function checkMerchantOrderStatus()
    {
        $validStatus   = array('on-hold', 'pending', 'failed');
        $isValidStatus = apply_filters(
            'woocommerce_valid_order_statuses_for_payment_complete',
            $validStatus,
            $this
        );

        if (!$this->woocommerceOrder->has_status($isValidStatus)) {
            throw new AlreadyProcessedException();
        }
    }

    /**
     * @throws AmountMismatchException
     */
    private function validateAmount()
    {
        $pagantisAmount = $this->pagantisOrder->getShoppingCart()->getTotalAmount();
        $wcAmount = intval(strval(100 * $this->woocommerceOrder->get_total()));
        if ($pagantisAmount != $wcAmount) {
            throw new AmountMismatchException($pagantisAmount, $wcAmount);
        }
    }

    /**
     * @throws Exception
     */
    private function processMerchantOrder()
    {
        $this->saveOrder();
        $this->updateBdInfo();
    }

    /**
     * @return false|string
     * @throws UnknownException
     */
    private function confirmPagantisOrder()
    {
        try {
            $this->pagantisOrder = $this->orderClient->confirmOrder($this->pagantisOrderId);
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
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
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE $tableName (id int, order_id varchar(50), wc_order_id varchar(50), 
                  UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Check if logs table exists
     */
    private function checkDbLogTable()
    {
        global $wpdb;
        $tableName = $wpdb->prefix.self::LOGS_TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $tableName ( id int NOT NULL AUTO_INCREMENT, log text NOT NULL, 
                    createdAt timestamp DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
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
    private function checkPagantisStatus($statusArray)
    {
        $pagantisStatus = array();
        foreach ($statusArray as $status) {
            $pagantisStatus[] = constant("\Pagantis\OrdersApiClient\Model\Order::STATUS_$status");
        }

        if ($this->pagantisOrder instanceof \Pagantis\OrdersApiClient\Model\Order) {
            $payed = in_array($this->pagantisOrder->getStatus(), $pagantisStatus);
            if (!$payed) {
                if ($this->pagantisOrder instanceof \Pagantis\OrdersApiClient\Model\Order) {
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
    /** STEP 6 CMOS - Check Merchant Order Status */
    /** STEP 7 VA - Validate Amount */
    /** STEP 8 PMO - Process Merchant Order */
    /**
     * @throws \Exception
     */
    private function saveOrder()
    {
        global $woocommerce;
        $paymentResult = $this->woocommerceOrder->payment_complete();
        if ($paymentResult) {
            $this->woocommerceOrder->add_order_note($this->origin);
            $this->woocommerceOrder->reduce_order_stock();
            $this->woocommerceOrder->save();

            $woocommerce->cart->empty_cart();
            sleep(3);
        } else {
            throw new UnknownException('Order can not be saved');
        }
    }

    /**
     * Save the merchant order_id with the related identification
     */
    private function updateBdInfo()
    {
        global $wpdb;

        $this->checkDbTable();
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;

        $wpdb->update(
            $tableName,
            array('wc_order_id'=>$this->woocommerceOrderId),
            array('id' => $this->woocommerceOrderId),
            array('%s'),
            array('%d')
        );
    }

    /** STEP 9 CPO - Confirmation Pagantis Order */
    private function rollbackMerchantOrder()
    {
        $this->woocommerceOrder->update_status('pending', __('Pending payment', 'woocommerce'));
    }

    /**
     * @param $exceptionMessage
     *
     * @throws \Zend_Db_Exception
     */
    private function insertLog($exception)
    {
        global $wpdb;

        if ($exception instanceof \Exception) {
            $this->checkDbLogTable();
            $logEntry= new LogEntry();
            $logEntryJson = $logEntry->error($exception)->toJson();

            $tableName = $wpdb->prefix.self::LOGS_TABLE;
            $wpdb->insert($tableName, array('log' => $logEntryJson));
        }
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
