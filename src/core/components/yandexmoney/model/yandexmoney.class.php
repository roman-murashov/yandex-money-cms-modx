<?php

/**
 * YandexMoney for MODX Revo
 *
 * Payment
 *
 * @author YandexMoney
 * @package yandexmoney
 * @version 1.3.0
 */


class Yandexmoney
{
    /** @var int Оплата через yandex.деньги вообще не используется */
    const MODE_NONE = 0;

    /** @var int Оплата производится через Яндекс.Кассу */
    const MODE_KASSA = 1;

    /** @var int Оплата производится через Яндекс.Деньги */
    const MODE_MONEY = 2;

    /** @var int Оплата производится через Яндекс.Платёжку */
    const MODE_BILLING = 3;

    /** @var int Какой способ оплаты используется, одна из констант MODE_XXX */
    private $mode;

    private $paymode;

    public $email = false;
    public $phone = false;

    public $test_mode;
    public $org_mode;

    public $orderId;
    public $orderTotal;
    public $userId;

    public $successUrl;
    public $failUrl;

    public $reciver;
    public $formcomment;
    public $short_dest;
    public $writable_targets = 'false';
    public $comment_needed = 'true';
    public $label;
    public $quickpay_form = 'shop';
    public $payment_type = '';
    public $targets;
    public $sum;
    public $comment;
    public $need_fio = 'true';
    public $need_email = 'true';
    public $need_phone = 'true';
    public $need_address = 'true';

    public $shopid;
    public $scid;
    public $account;
    public $password;

    public $method_ym;
    public $method_cards;
    public $method_cash;
    public $method_mobile;
    public $method_wm;
    public $method_ab;
    public $method_sb;
    public $method_ma;
    public $method_pb;

    public $pay_method;

    /** @var string Идентификатор магазина в Яндекс.Платёжке */
    public $ya_billing_id;

    /** @var string Описание платежа, заданное из админки */
    public $ya_billing_purpose;

    /** @var string ФИО плательщика, переданное из запроса пользователя */
    public $ya_billing_fio;

    function __construct(modX &$modx, $config = array())
    {
        $this->mode = self::MODE_NONE;
        switch ($config['mode']) {
            case 1:
                $this->mode = self::MODE_MONEY;
                break;
            case 2:
            case 3:
                $this->mode = self::MODE_KASSA;
                break;
            case 4:
                $this->mode = self::MODE_BILLING;
                break;
        }

        $this->org_mode = ($config['mode'] == 2 || $config['mode'] == 3);
        $this->test_mode = ($config['testmode'] == 1);
        $this->shopid = ($config['testmode'] == 1);
        $this->paymode = (bool) ($config['mode'] == 3);

        if (isset($config) && is_array($config)){
            foreach ($config as $k=>$v){
                if ($k != 'mode') {
                    $this->$k = $v;
                }
            }
        }
        $this->modx =& $modx;
        $this->config = $config;

        //$this->modx->addPackage('yandexmoney', $this->config['corePath'].'model/');
    }

    public function getFormUrl()
    {
        if ($this->mode != self::MODE_BILLING) {
            $demo = ($this->test_mode) ? 'demo' : '';
            $mode = ($this->org_mode) ? '/eshop.xml' : '/quickpay/confirm.xml';
            return 'https://' . $demo . 'money.yandex.ru' . $mode;
        }
        return 'https://money.yandex.ru/fastpay/confirm';
    }

    public function checkPayMethod()
    {
        if ($this->mode == self::MODE_BILLING) {
            $fio = explode(' ', $_POST['ya-billing-fio']);
            if (count($fio) != 3) {
                return false;
            }
            foreach ($fio as $index => $value) {
                $value = trim($value);
                if (empty($value)) {
                    return false;
                }
                $fio[$index] = $value;
            }
            $this->ya_billing_fio = implode(' ', $fio);
            return true;
        }
        return (in_array($this->pay_method, array('PC','AC','MC','GP','WM','AB','SB','MA','PB','QW','QP')) || $this->paymode);
    }

    public function getSelectHtml()
    {
        $result = json_encode(array('mode' => $this->mode));
        if ($this->mode == self::MODE_MONEY) {
            return "<option value=''>Яндекс.Касса (банковские карты, электронные деньги и другое)</option>";
        } elseif ($this->mode == self::MODE_KASSA) {
            $list_methods = array(
                'ym'     => array('PC' => 'Оплата из кошелька в Яндекс.Деньгах'),
                'cards'  => array('AC' => 'Оплата с произвольной банковской карты'),
                'cash'   => array('GP' => 'Оплата наличными через кассы и терминалы'),
                'mobile' => array('MC' => 'Платеж со счета мобильного телефона'),
                'ab'     => array('AB' => 'Оплата через Альфа-Клик'),
                'sb'     => array('SB' => 'Оплата через Сбербанк: оплата по SMS или Сбербанк Онлайн'),
                'wm'     => array('WM' => 'Оплата из кошелька в системе WebMoney'),
                'ma'     => array('MA' => 'Оплата через MasterPass'),
                'pb'     => array('PB' => 'Оплата через интернет-банк Промсвязьбанка'),
                'qw'     => array('QW' => 'Оплата через QIWI Wallet'),
                'qp'     => array('QP' => 'Оплата через доверительный платеж (Куппи.ру)')
            );
            $output = '';
            foreach ($list_methods as $long_name => $method_desc) {
                $by_default = (in_array($long_name, array('ym', 'cards'))) ? true : $this->org_mode;
                if ($this->{'method_' . $long_name} == 1 && $by_default) {
                    $output .= '<option value="' . key($method_desc) . '"';
                    if ($this->pay_method == key($method_desc))
                        $output .= ' selected ';
                    $output .= '>' . $method_desc[key($method_desc)] . '</option>';
                }
            }
            return $output;
        } elseif ($this->mode == self::MODE_BILLING) {
            return "<option value='4'>Яндекс.Платежка (банковские карты, кошелек)</option>";
        }
        return $result;
    }

    public function createFormHtml()
    {
        global $modx;

        /** @var SHKorder $order */
        $order = $modx->getObject('SHKorder',array('id' => $this->orderId));

        if (isset($this->config['ya_kassa_send_check']) && $this->config['ya_kassa_send_check']) {
            $receipt = array(
                'customerContact' => $order->_fields['email'],
                'items' => array(),
            );

            if ($content = unserialize($order->_fields['content'])) {
                foreach ($content as $item) {
                    $receipt['items'][] = array(
                        'quantity' => $item['count'],
                        'text' => substr($item['name'], 0, 128),
                        'tax' => ($this->config['tax_id'] ? $this->config['tax_id'] : 1),
                        'price' => array(
                            'amount' => number_format($item['price'], 2, '.', ''),
                            'currency' => 'RUB'
                        ),
                    );
                }
            }

            if ($order->_fields['delivery']) {
                $receipt['items'][] = array(
                    'quantity' => 1,
                    'text' => substr('testName', 0, 128),
                    'tax' => ($this->config['tax_id'] ? $this->config['tax_id'] : 1),
                    'price' => array(
                        'amount' => number_format(0, 2, '.', ''),
                        'currency' => 'RUB'
                    ),
                );
            }
        }

        $site_url = $modx->config['site_url'];
        $payType = ($this->paymode)?'':$this->pay_method;
        $addInfo = ($this->email!==false)?'<input type="hidden" name="cps_email" value="'.$this->email.'" >':'';
        $addInfo .= ($this->phone!==false)?'<input type="hidden" name="cps_phone" value="'.$this->phone.'" >':'';
        $html = '<form method="POST" action="'.$this->getFormUrl().'"  id="paymentform" name = "paymentform">';
        if ($this->mode == self::MODE_KASSA) {
            $html .= '<input type="hidden" name="paymentType" value="'.$payType.'" />
                   <input type="hidden" name="shopid" value="'.$this->shopid.'">
                   <input type="hidden" name="scid" value="'.$this->scid.'">
                   <input type="hidden" name="orderNumber" value="'.$this->orderId.'">
                   <input type="hidden" name="sum" value="'.$this->orderTotal.'" data-type="number" >
                   <input type="hidden" name="customerNumber" value="'.$this->userId.'" >'.
                    $addInfo.'
                    <input type="hidden" name="shopSuccessUrl" value="'.$this->successUrl.'">
                    <input type="hidden" name="shopFailUrl" value="'.$this->failUrl.'">
                    ';

            if (isset($this->config['ya_kassa_send_check']) && $this->config['ya_kassa_send_check']) {
                $html .= '<input type="hidden" name="ym_merchant_receipt" value=\''.json_encode($receipt).'\'>';
            }
        } elseif ($this->mode == self::MODE_MONEY) {
            $html .= '  <input type="hidden" name="receiver" value="'.$this->account.'">
                       <input type="hidden" name="formcomment" value="Order '.$this->orderId.'">
                       <input type="hidden" name="short-dest" value="Order '.$this->orderId.'">
                       <input type="hidden" name="writable-targets" value="'.$this->writable_targets.'">
                       <input type="hidden" name="comment-needed" value="'.$this->comment_needed.'">
                       <input type="hidden" name="label" value="'.$this->orderId.'">
                       <input type="hidden" name="quickpay-form" value="'.$this->quickpay_form.'">
                       <input type="hidden" name="paymentType" value="'.$this->pay_method.'">
                       <input type="hidden" name="targets" value="Заказ '.$this->orderId.'">
                       <input type="hidden" name="sum" value="'.$this->orderTotal.'" data-type="number" >
                       <input type="hidden" name="comment" value="'.$this->comment.'" >
                       <input type="hidden" name="need-fio" value="'.$this->need_fio.'">
                       <input type="hidden" name="need-email" value="'.$this->need_email.'" >
                       <input type="hidden" name="need-phone" value="'.$this->need_phone.'">
                       <input type="hidden" name="need-address" value="'.$this->need_address.'">
                        <input type="hidden" name="successUrl" value="'.$site_url.'assets/snippets/yandexmoney/callback.php?success=1">';
        } elseif ($this->mode == self::MODE_BILLING) {
            $narrative = $this->parsePlaceholders($this->ya_billing_purpose, $order);
            $html .= '  <input type="hidden" name="formId" value="'.$this->ya_billing_id.'" />
                       <input type="hidden" name="narrative" value="'.htmlspecialchars($narrative).'" />
                       <input type="hidden" name="fio" value="'.htmlspecialchars($this->ya_billing_fio).'" />
                       <input type="hidden" name="sum" value="'.$this->orderTotal.'" />
                       <input type="hidden" name="quickPayVersion" value="2" />';
            $this->updateOrderStatus($order, $this->config['ya_billing_status']);
        }
        $html .= '<input type="hidden" name="cms_name" value="modx" >
                </form>
                <script type="text/javascript">
                        document.getElementById("paymentform").submit();
                    </script>';

        echo $html;
        exit;
    }



    public function checkSign($callbackParams)
    {
        if ($this->org_mode) {
            $string = $callbackParams['action'].';'.$callbackParams['orderSumAmount'].';'.$callbackParams['orderSumCurrencyPaycash'].';'.$callbackParams['orderSumBankPaycash'].';'.$callbackParams['shopId'].';'.$callbackParams['invoiceId'].';'.$callbackParams['customerNumber'].';'.$this->password;
            $md5 = strtoupper(md5($string));
            return ($callbackParams['md5']==$md5);
        } else {
            $string = $callbackParams['notification_type'].'&'.$callbackParams['operation_id'].'&'.$callbackParams['amount'].'&'.$callbackParams['currency'].'&'.$callbackParams['datetime'].'&'.$callbackParams['sender'].'&'.$callbackParams['codepro'].'&'.$this->password.'&'.$callbackParams['label'];
            $check = (sha1($string) == $callbackParams['sha1_hash']);
            if (!$check){
                header('HTTP/1.0 401 Unauthorized');
                return false;
            }
            return true;
        }
    }

    public function sendCode($callbackParams, $code)
    {
        if (!$this->org_mode) {
            if ($code === 0) {
                header('HTTP/1.0 200 OK');
            } else {
                header('HTTP/1.0 401 Unauthorized');
            }
            return;
        }
        header("Content-type: text/xml; charset=utf-8");
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <'.$callbackParams['action'].'Response performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"/>';
        echo $xml;
    }

    /* оплачивает заказ */
    public function ProcessResult()
    {
        $callbackParams = $_POST;
        if ($this->checkSign($callbackParams)) {
            $order_id = ($this->org_mode)? intval($callbackParams["orderNumber"]):intval($callbackParams["label"]);
            if ($order_id) {
                $this->modx->addPackage('shopkeeper', MODX_CORE_PATH."components/shopkeeper/model/");
                $order = $this->modx->getObject('SHKorder',array('id'=>$order_id));
                $amount = number_format($order->get('price'),2,".",'');
                $pay_amount = number_format($callbackParams[($this->org_mode)?'orderSumAmount':'amount'], 2, '.', '');
                if ($pay_amount === $amount) {
                    if ($callbackParams['action'] == 'paymentAviso' || !$this->org_mode){
                        $order->set('status', 5);
                        $order->save();
                    }
                    $this->sendCode($callbackParams, 0);
                } else {
                    $this->sendCode($callbackParams, 100);
                }
            } else {
                $this->sendCode($callbackParams, 200);
            }
        } else {
            $this->sendCode($callbackParams, 1);
        }
    }

    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Преобразует шаблон назначения платежа в удобоваримую строку
     * @param string $template Шаблон назначения платежя
     * @param SHKorder $order Информация о заказе
     * @return string Строка для отправки в Яндекс.Деньги
     */
    private function parsePlaceholders($template, SHKorder $order)
    {
        $replace = array(
            '%order_id%' => $order->id,
        );
        foreach ($order->toArray() as $key => $value) {
            if (is_scalar($value)) {
                $replace['%' . $key . '%'] = $value;
            }
        }
        return strtr($template, $replace);
    }

    /**
     * Устанавливает новый статус исполнения заказа
     * @param SHKorder $order Инстанс изменяемого заказа
     * @param string $status Новый статус заказа
     */
    private function updateOrderStatus(SHKorder $order, $status)
    {
        if ($status > 0) {
            $order->set('status', $status);
            $order->save();
        }
    }
}
