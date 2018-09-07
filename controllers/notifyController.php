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
    const NOT_CONFIRMED = 'No se ha podido confirmar el pago';
    const ORDER_NOT_FOUND = 'No se ha podido encontrar la orden en la tienda';
    const ALREADY_PROCESSED = 'El pago ya ha sido procesado';
    const PAYMENT_COMPLETE = 'Pago completado';
    const PAYMENT_INCOMPLETE = 'Pago incompleto';
    const NO_ORDERID = 'No se ha podido recuperar el identificador del pedido en PagaMasTarde.';
    const WRONG_AMOUNT = 'La cantidad del pedido es incorrecta';
    const WRONG_STATUS = 'El estado del pedido es inválido';

    /** @var Array_ $notifyResult */
    protected $notifyResult;

    /** @var mixed $pmtOrder */
    protected $pmtOrder;

    /**
     * @var $string
     */
    public $origin;

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

    /**
     * @var $string
     */
    public $order;

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param $order
     *
     * @throws Exception
     */
    public function setOrder($order)
    {
        if (get_class($order)=='WC_Order') {
            $this->order = $order;
        } else {
            throw new Exception('La orden no existe en esta tienda');
        }
    }

    /**
     * Validation vs PmtClient
     *
     * @return array|Array_
     * @throws Exception
     */
    public function processInformation()
    {
        require_once(__ROOT__.'/vendor/autoload.php');
        global $woocommerce;
        //Notification_error = true => Status 400 - Retry. = false => Status 200 - OK.
        $this->notifyResult = array('notification_error'=>true,'notification_message'=>self::NOT_CONFIRMED);
        if (!$this->getOrder()->get_id()) {
            $this->notifyResult['notification_message'] = self::ORDER_NOT_FOUND;
            $this->notifyResult['notification_error'] = false;
        } else {
            $order         = new WC_Order($this->getOrder()->get_id());
            $validStatus   = array('on-hold', 'pending', 'failed');
            $isValidStatus = apply_filters(
                'woocommerce_valid_order_statuses_for_payment_complete',
                $validStatus,
                $this
            );

            if (!$order->has_status($isValidStatus)) {
                $this->notifyResult['notification_message'] = self::ALREADY_PROCESSED;
                $this->notifyResult['notification_error']   = false;
            } else {
                if ($pmtOrderId = $this->getPmtOrderId()) {
                    $cfg       = get_option('woocommerce_paylater_settings');
                    $orderClient = new Client($cfg['public_key'], $cfg['secret_key']);
                    $this->pmtOrder = $orderClient->getOrder($pmtOrderId);
                    if ($this->checkPmtStatus(array('CONFIRMED','AUTHORIZED'))) {
                        if ($this->comparePrices()) {
                            $paymentResult = $order->payment_complete();
                            if ($paymentResult) {
                                $order->add_order_note($this->origin);
                                $order->reduce_order_stock();
                                $woocommerce->cart->empty_cart();
                                $this->pmtOrder = $orderClient->confirmOrder($pmtOrderId);
                                if ($this->checkPmtStatus(array('CONFIRMED'))) {
                                    $this->notifyResult['notification_error'] = false;
                                    $this->notifyResult['notification_message'] =  self::PAYMENT_COMPLETE;
                                }
                            } else {
                                $this->setToFailed();
                                $this->notifyResult['notification_error'] = true;
                                $this->notifyResult['notification_message'] = self::PAYMENT_INCOMPLETE;
                            }
                        }
                    }
                }
            }
        }

        return $this->notifyResult;
    }

    /**
     * Set order to failed
     * @return bool
     */
    public function setToFailed()
    {
        $order = $this->getOrder();
        if (get_class($order)=='WC_Order') {
            $order->update_status('failed', __('Error en el pago con Paga+Tarde', 'woocommerce'));
        }
        return true;
    }

    /**
     * Set order to pending
     * @return bool
     */
    public function setToPending()
    {
        $order = $this->getOrder();
        if (get_class($order)=='WC_Order') {
            $order->update_status('pending', __('Pending payment', 'woocommerce'));
        }
        return true;
    }

    /**
     * Get the pmt order id from db
     *
     * @return bool
     */
    private function getPmtOrderId()
    {
        global $wpdb;
        $this->checkDbTable();
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;

        //Check if id exists
        $queryResult = $wpdb->get_row("select order_id from $tableName where id='".$this->getOrder()->get_id()."'");
        if ($queryResult->order_id == '') {
            $this->notifyResult['notification_error'] = false;
            $this->notifyResult['notification_message'] = self::NO_ORDERID;
            return false;
        }

        return $queryResult->order_id;
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
            $sql             = "CREATE TABLE $tableName ( id int, order_id varchar(50), 
                  UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * @param $statusArray
     *
     * @return bool
     */
    private function checkPmtStatus($statusArray)
    {
        $pmtStatus = array();
        foreach ($statusArray as $status) {
            $pmtStatus[] = constant("\PagaMasTarde\OrdersApiClient\Model\Order::STATUS_$status");
        }
        $payed = in_array($this->pmtOrder->getStatus(), $pmtStatus);
        if (!$payed) {
            $this->notifyResult['notification_error'] = true;
            $this->notifyResult['notification_message'] = self::WRONG_STATUS.' => '.$this->pmtOrder->getStatus();
            $responseStatus = false;
        } else {
            $responseStatus = true;
        }

        return $responseStatus;
    }

    /**
     * @throws \Exception
     */
    private function comparePrices()
    {
        $pmtAmount = $this->pmtOrder->getShoppingCart()->getTotalAmount();
        $wcAmount = intval(strval(100 * $this->getOrder()->get_total()));
        if ($pmtAmount != $wcAmount) {
            $this->notifyResult['notification_error'] = true;
            $this->notifyResult['notification_message'] = self::WRONG_AMOUNT;
            $pricesResponse = false;
        } else {
            $pricesResponse = true;
        }
        return $pricesResponse;
    }
}
