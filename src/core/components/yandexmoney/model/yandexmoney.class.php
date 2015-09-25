<?php

/**
 * YandexMoney for MODX Revo
 *
 * Payment
 *
 * @author YandexMoney
 * @package yandexmoney
 * @version 1.2.0
 */


class Yandexmoney {

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
    
    function __construct(modX &$modx,$config = array()) {
		$this->org_mode = ($config['mode'] == 2);
		$this->test_mode = ($config['testmode'] == 1);
		$this->shopid = ($config['testmode'] == 1);

		
		if (isset($config) && is_array($config)){
			foreach ($config as $k=>$v){
				$this->$k = $v;
			}
		}

		

		$this->modx =& $modx;
		
		//$this->modx->addPackage('yandexmoney', $this->config['corePath'].'model/');		
    }
    

	/**
	 * Переводит статус заказа в "Оплата получена" (Shopkeeper)
	 * 
	 */
	function shkOrderPaid($order_id){
		
		if($order_id){
			
			$this->modx->addPackage('shopkeeper', MODX_CORE_PATH."components/shopkeeper/model/");
			
			$order = $this->modx->getObject('SHKorder',$order_id);
			if($order){
				$order->set('status',$this->modx->config['yandexmonay.payStatusOut']);
				$order->save();
				return true;	
			}	
		}
		return false;
		
	}


	public function getFormUrl(){
		if (!$this->org_mode){
			return $this->individualGetFormUrl();
		}else{
			return $this->orgGetFormUrl();
		}
	}

	public function individualGetFormUrl(){
		if ($this->test_mode){
			return 'https://demomoney.yandex.ru/quickpay/confirm.xml';
		}else{
			return 'https://money.yandex.ru/quickpay/confirm.xml';
		}
	}

	public function orgGetFormUrl(){
		if ($this->test_mode){
            return 'https://demomoney.yandex.ru/eshop.xml';
        } else {
            return 'https://money.yandex.ru/eshop.xml';
        }
	}

	public function checkPayMethod(){
		if (in_array($this->pay_method, array('PC','AC','MC','GP','WM','AB','SB','MA','PB','QW','QP'))) return TRUE;
		return FALSE;
	}

	public function getSelectHtml(){
		$list_methods=array(
			'ym'=>array('PC'=>'Оплата из кошелька в Яндекс.Деньгах'),
			'cards'=>array('AC'=>'Оплата с произвольной банковской карты'),
			'cash'=>array('GP'=>'Оплата наличными через кассы и терминалы'),
			'mobile'=>array('MC'=>'Платеж со счета мобильного телефона'),
			'ab'=>array('AB'=>'Оплата через Альфа-Клик'),
			'sb'=>array('SB'=>'Оплата через Сбербанк: оплата по SMS или Сбербанк Онлайн'),
			'wm'=>array('WM'=>'Оплата из кошелька в системе WebMoney'),
			'ma'=>array('MA'=>'Оплата через MasterPass'),
			'pb'=>array('PB'=>'Оплата через интернет-банк Промсвязьбанка'),
			'qw'=>array('QW'=>'Оплата через QIWI Wallet'),
			'qp'=>array('QP'=>'Оплата через доверительный платеж (Куппи.ру)')
		);
		foreach ($list_methods as $long_name=>$method_desc){
			$by_default=(in_array($long_name, array('ym','cards')))?true:$this->org_mode;
			if ($this->{'method_'.$long_name} == 1 && $by_default) {
				$output .= '<option value="'.key($method_desc).'"';
				if ($this->pay_method == key($method_desc)) $output.=' selected ';
				$output .= '>'.$method_desc[key($method_desc)].'</option>';
			}
		}
		return $output;
	}

	public function createFormHtml(){
		if ($this->org_mode){
			$html = '
				<form method="POST" action="'.$this->getFormUrl().'"  id="paymentform" name = "paymentform">
				   <input type="hidden" name="paymentType" value="'.$this->pay_method.'" />
				   <input type="hidden" name="shopid" value="'.$this->shopid.'">
				   <input type="hidden" name="scid" value="'.$this->scid.'">
				   <input type="hidden" name="orderNumber" value="'.$this->orderId.'">
				   <input type="hidden" name="sum" value="'.$this->orderTotal.'" data-type="number" >
				   <input type="hidden" name="customerNumber" value="'.$this->userId.'" >
				   <input type="hidden" name="cms_name" value="modx" >
				   <input type="hidden" name="shopSuccessURL" value="'.$this->successUrl.'" >
				   <input type="hidden" name="shopFailURL" value="'.$this->failUrl.'" >					
				</form>';
		}else{
			$html = '<form method="POST" action="'.$this->getFormUrl().'"  id="paymentform" name = "paymentform">
					   <input type="hidden" name="receiver" value="'.$this->account.'">
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
						<input type="hidden" name="SuccessUrl" value="'.$this->successUrl.'" >
					</form>';
		}
		$html .= '<script type="text/javascript">
						document.getElementById("paymentform").submit();
					</script>';
		return $html;
	}


	public function checkSign($callbackParams){
		$string = $callbackParams['action'].';'.$callbackParams['orderSumAmount'].';'.$callbackParams['orderSumCurrencyPaycash'].';'.$callbackParams['orderSumBankPaycash'].';'.$callbackParams['shopId'].';'.$callbackParams['invoiceId'].';'.$callbackParams['customerNumber'].';'.$this->password;
		$md5 = strtoupper(md5($string));
		return ($callbackParams['md5']==$md5);
	}

	public function sendAviso($callbackParams, $code){
		header("Content-type: text/xml; charset=utf-8");
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<paymentAvisoResponse performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"/>';
		echo $xml;
	}

	public function sendCode($callbackParams, $code){
		header("Content-type: text/xml; charset=utf-8");
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<checkOrderResponse performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"/>';
		echo $xml;
	}

	public function checkOrder($callbackParams, $sendCode=FALSE, $aviso=FALSE){ 
		
		if ($this->checkSign($callbackParams)){
			$code = 0;
		}else{
			$code = 1;
		}
		
		if ($sendCode){
			if ($aviso){
				$this->sendAviso($callbackParams, $code);
			}else{
				$this->sendCode($callbackParams, $code);
			}
			exit;
		}else{
			return $code;
		}
	}

	public function individualCheck($callbackParams){
		$string = $callbackParams['notification_type'].'&'.$callbackParams['operation_id'].'&'.$callbackParams['amount'].'&'.$callbackParams['currency'].'&'.$callbackParams['datetime'].'&'.$callbackParams['sender'].'&'.$callbackParams['codepro'].'&'.$this->password.'&'.$callbackParams['label'];
		$check = (sha1($string) == $callbackParams['sha1_hash']);
		if (!$check){
			header('HTTP/1.0 401 Unauthorized');
			return false;
		}
		return true;
	
	}

	/* оплачивает заказ */
	public function ProcessResult()
	{
		$callbackParams = $_POST;
		$order_id = false;
		if ($this->org_mode){
			if ($callbackParams['action'] == 'checkOrder'){
				$code = $this->checkOrder($callbackParams);
				$this->sendCode($callbackParams, $code);
				$order_id = (int)$callbackParams["orderNumber"];
			}
			if ($callbackParams['action'] == 'paymentAviso'){
				$this->checkOrder($callbackParams, TRUE, TRUE);
			}
		}else{
			$check = $this->individualCheck($callbackParams);
			
			if (!$check){
				
			}else{
				$order_id = (int)$callbackParams["label"];
			}
		}
		
		return $order_id;
	}


}