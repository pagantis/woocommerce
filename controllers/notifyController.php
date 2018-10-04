<?php

use PagaMasTarde\OrdersApiClient\Client;

if (!defined('ABSPATH')) {
    exit;
}

class WcPaylaterNotify extends WcPaylaterGateway
{
    /**
     * EXCEPTION RESPONSES
     */
    const CC_ERR_MSG = 'Unable to block resource';
    const CC_NO_QUOTE = 'OrderId not found';
    const CC_NO_VALIDATE ='Validation in progress, try again later';
    const GMO_ERR_MSG = 'Merchant Order Not Found';
    const GPOI_ERR_MSG = 'Pmt Order Not Found';
    const GPOI_NO_ORDERID = 'We can not get the PagaMasTarde identification in database.';
    const GPO_ERR_MSG = 'Unable to get Order';
    const COS_ERR_MSG = 'Order status is not authorized';
    const COS_WRONG_STATUS = 'Invalid Pmt status';
    const CMOS_ERR_MSG = 'Merchant Order status is invalid';
    const CMOS_ALREADY_PROCESSED = 'Cart already processed.';
    const VA_ERR_MSG = 'Amount conciliation error';
    const VA_WRONG_AMOUNT = 'Wrong order amount';
    const PMO_ERR_MSG = 'Unknown Error';
    const CPO_ERR_MSG = 'Order not confirmed';
    const CPO_OK_MSG = 'Order confirmed';

    /** @var Array_ $notifyResult */
    protected $notifyResult;

    /** @var mixed $pmtOrder */
    protected $pmtOrder;

    /** @var $string $origin */
    public $origin;

    /** @var $string */
    public $order;

    /** @var mixed $woocommerceOrderId */
    protected $woocommerceOrderId;

    /** @var mixed $cfg */
    protected $cfg;

    /** @var Client $orderClient */
    protected $orderClient;

    /** @var  WC_Order $woocommerceOrder */
    protected $woocommerceOrder;

    /** @var mixed $pmtOrderId */
    protected $pmtOrderId;

    /**
     * Validation vs PmtClient
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
            $this->getPmtOrderId();
            $this->getPmtOrder();
            $this->checkOrderStatus();
            $this->checkMerchantOrderStatus();
            $this->validateAmount();
            $this->processMerchantOrder();
        } catch (\Exception $exception) {
            $this->insertLog($exception);
            $exception = unserialize($exception->getMessage());
            $status = $exception->status;
            $response = array();
            $response['timestamp'] = time();
            $response['order_id']= $this->woocommerceOrderId;
            $response['result'] = $exception->result;
            $response['result_description'] = $exception->result_description;
            $response = json_encode($response);
        }
        try {
            if (!isset($response)) {
                $response = $this->confirmPmtOrder();
                $status = isset($response['status']) ? $response['status'] : 200;
                $response = json_encode($response);
            }
        } catch (\Exception $exception) {
            $this->insertLog($exception);
            $this->rollbackMerchantOrder();
            $exception = unserialize($exception->getMessage());
            $status = $exception->status;
            $response = array();
            $response['timestamp'] = time();
            $response['order_id']= $this->woocommerceOrderId;
            $response['result'] = self::CPO_ERR_MSG;
            $response['result_description'] = $exception->result_description;
            $response = json_encode($response);
        }

        return array('response'=>$response, 'status'=>$status);
    }

    /**
     * COMMON FUNCTIONS
     */

    /**
     * @throws Exception
     */
    private function readLogs()
    {
        try {
            global $wpdb;
            $this->checkDbLogTable();
            $tableName = $wpdb->prefix.self::LOGS_TABLE;
            $queryResult = $wpdb->get_results("select * from $tableName");
            var_dump("<pre>", $queryResult);
            die;
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='429';
            $exceptionObject->result= self::CC_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    /**
     * @throws Exception
     */
    private function checkConcurrency()
    {
        try {
            $this->getOrderId();
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='429';
            $exceptionObject->result= self::CC_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    /**
     * @throws Exception
     */
    private function getMerchantOrder()
    {
        try {
            $this->woocommerceOrder = new WC_Order($this->woocommerceOrderId);
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='404';
            $exceptionObject->result= self::GMO_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    /**
     * @throws Exception
     */
    private function getPmtOrderId()
    {
        try {
            $this->getPmtOrderIdDb();
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='404';
            $exceptionObject->result= self::GPOI_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    private function getPmtOrder()
    {
        try {
            $this->cfg = get_option('woocommerce_paylater_settings');
            $this->orderClient = new Client($this->cfg['public_key'], $this->cfg['secret_key']);
            $this->pmtOrder = $this->orderClient->getOrder($this->pmtOrderId);
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='400';
            $exceptionObject->result= self::GPO_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    private function checkOrderStatus()
    {
        try {
            $this->checkPmtStatus(array('AUTHORIZED'));
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            if ($this->getWoocommerceOrderId()!='') {
                $exceptionObject->status='200';
                $exceptionObject->result= self::CMOS_ALREADY_PROCESSED;
                $exceptionObject->result_description = self::CMOS_ALREADY_PROCESSED;
            } else {
                $exceptionObject->status='403';
                $exceptionObject->result= self::COS_ERR_MSG;
                $exceptionObject->result_description = $e->getMessage();
            }
            throw new \Exception(serialize($exceptionObject));
        }
    }

    private function checkMerchantOrderStatus()
    {
        try {
            $this->checkCartStatus();
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='409';
            $exceptionObject->result= self::CMOS_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    private function validateAmount()
    {
        try {
            $this->comparePrices();
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='409';
            $exceptionObject->result= self::VA_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    private function processMerchantOrder()
    {
        try {
            $this->saveOrder();
            $this->updateBdInfo();
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='500';
            $exceptionObject->result= self::PMO_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
    }

    private function confirmPmtOrder()
    {
        try {
            $this->pmtOrder = $this->orderClient->confirmOrder($this->pmtOrderId);
        } catch (\Exception $e) {
            $exceptionObject = new \stdClass();
            $exceptionObject->method= __FUNCTION__;
            $exceptionObject->status='500';
            $exceptionObject->result= self::CPO_ERR_MSG;
            $exceptionObject->result_description = $e->getMessage();
            throw new \Exception(serialize($exceptionObject));
        }
        $response = array();
        $response['status'] = '200';
        $response['timestamp'] = time();
        $response['order_id']= $this->woocommerceOrderId;
        $response['result'] = self::CPO_OK_MSG;
        return $response;
    }
    /**
     * UTILS FUNCTIONS
     */
    /** STEP 1 CC - Check concurrency */
    /**
     * @throws \Exception
     */
    private function getOrderId()
    {
        $this->woocommerceOrderId = $_GET['order-received'];
        if ($this->woocommerceOrderId == '') {
            throw new \Exception(self::CC_NO_QUOTE);
        }
    }

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
            $sql = "CREATE TABLE $tableName ( id int NOT NULL AUTO_INCREMENT, log text NOT NULL, createdAt timestamp DEFAULT CURRENT_TIMESTAMP,  
                  UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        return;
    }

    /** STEP 2 GMO - Get Merchant Order */
    /** STEP 3 GPOI - Get Pmt OrderId */
    /**
     * @throws \Exception
     */
    private function getPmtOrderIdDb()
    {
        global $wpdb;
        $this->checkDbTable();
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;
        $queryResult = $wpdb->get_row("select order_id from $tableName where id='".$this->woocommerceOrderId."'");
        $this->pmtOrderId = $queryResult->order_id;

        if ($this->pmtOrderId == '') {
            throw new \Exception(self::GPOI_NO_ORDERID);
        }
    }
    /** STEP 4 GPO - Get Pmt Order */
    /** STEP 5 COS - Check Order Status */
    /**
     * @param $statusArray
     *
     * @throws \Exception
     */
    private function checkPmtStatus($statusArray)
    {
        $pmtStatus = array();
        foreach ($statusArray as $status) {
            $pmtStatus[] = constant("\PagaMasTarde\OrdersApiClient\Model\Order::STATUS_$status");
        }
        $payed = in_array($this->pmtOrder->getStatus(), $pmtStatus);
        if (!$payed) {
            throw new \Exception(self::CMOS_ERR_MSG."=>".$this->pmtOrder->getStatus());
        }
    }
    /** STEP 6 CMOS - Check Merchant Order Status */
    /**
     * @throws \Exception
     */
    private function checkCartStatus()
    {
        $validStatus   = array('on-hold', 'pending', 'failed');
        $isValidStatus = apply_filters(
            'woocommerce_valid_order_statuses_for_payment_complete',
            $validStatus,
            $this
        );

        if (!$this->woocommerceOrder->has_status($isValidStatus)) {
            throw new \Exception(self::CMOS_ALREADY_PROCESSED);
        }
    }

    private function getWoocommerceOrderId()
    {
        global $wpdb;
        $tableName   = $wpdb->prefix.self::ORDERS_TABLE;
        $queryResult = $wpdb->get_row("select wc_order_id from $tableName where id='$this->quoteId' and order_id='$this->pmtOrderId'");
        return $queryResult['wc_order_id'];
    }

    /** STEP 7 VA - Validate Amount */
    /**
     * @throws \Exception
     */
    private function comparePrices()
    {
        $pmtAmount = $this->pmtOrder->getShoppingCart()->getTotalAmount();
        $wcAmount = intval(strval(100 * $this->woocommerceOrder->get_total()));
        if ($pmtAmount != $wcAmount) {
            throw new \Exception(self::VA_ERR_MSG);
        }
    }

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
            $woocommerce->cart->empty_cart();
            $this->woocommerceOrder->save();
            sleep(3);
        } else {
            throw new \Exception(self::PMO_ERR_MSG);
        }
    }

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

    /** STEP 9 CPO - Confirmation Pmt Order */
    private function rollbackMerchantOrder()
    {
        $this->woocommerceOrder->update_status('pending', __('Pending payment', 'woocommerce'));
    }

    /**
     * @param $exceptionMessage
     *
     * @throws \Zend_Db_Exception
     */
    private function insertLog($exceptionMessage)
    {
        global $wpdb;
        if ($exceptionMessage instanceof \Exception) {
            $this->checkDbLogTable();
            $logObject          = new \stdClass();
            $logObject->message = $exceptionMessage->getMessage();
            $logObject->code    = $exceptionMessage->getCode();
            $logObject->line    = $exceptionMessage->getLine();
            $logObject->file    = $exceptionMessage->getFile();
            $logObject->trace   = $exceptionMessage->getTraceAsString();

            $tableName = $wpdb->prefix.self::LOGS_TABLE;
            $wpdb->insert($tableName, array('log' => json_encode($logObject)));
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
