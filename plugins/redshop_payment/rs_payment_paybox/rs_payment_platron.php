<?php
/**
 * @package     RedSHOP
 * @subpackage  Plugin
 *
 * @copyright   Copyright (C) 2005 - 2013 redCOMPONENT.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');
include 'PG_Signature.php';

class plgRedshop_paymentrs_payment_paybox extends JPlugin
{
	public $_table_prefix = null;

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for
	 * plugins because func_get_args ( void ) returns a copy of all passed arguments
	 * NOT references.  This causes problems with cross-referencing necessary for the
	 * observer design pattern.
	 */
	public function plgRedshop_paymentrs_payment_paybox(&$subject)
	{
		// Load plugin parameters
		parent::__construct($subject);
		$this->_table_prefix = '#__redshop_';
		$this->_plugin = JPluginHelper::getPlugin('redshop_payment', 'rs_payment_paybox');
		$this->_params = new JRegistry($this->_plugin->params);
	}

	/**
	 * Plugin method with the same name as the event will be called automatically.
	 */
	public function onPrePayment($element, $data)
	{
		if ($element != 'rs_payment_paybox')
		{
			return;
		}

		if (empty($plugin))
		{
			$plugin = $element;
		}

		$app = JFactory::getApplication();
		$paymentpath = JPATH_SITE . '/plugins/redshop_payment/' . $plugin . '/' . $plugin . '/extra_info.php';
		include $paymentpath;
	}

	public function onNotifyPaymentrs_payment_paybox($element, $arrRequest)
	{
		error_reporting(E_ERROR | E_WARNING | E_PARSE);

		if ($element != 'rs_payment_paybox')
		{
			return;
		}

		$db = JFactory::getDbo();
		$request = JRequest::get('request');
		$Itemid = $request["Itemid"];

		$quickpay_parameters = $this->getparameters('rs_payment_paybox');
		$paymentinfo = $quickpay_parameters[0];
		$paymentparams = new JRegistry($paymentinfo->params);

		$objOrder = new order_functions;
		$orderStatuses = $objOrder->getOrderStatus();
		$orderInfo = $objOrder->getOrderDetails($arrRequest['pg_order_id']);
		$arrStatuses = array();
		foreach($orderStatuses as $objOrder){
			$arrStatuses[$objOrder->value] = $objOrder->text;
		}

		$thisScriptName = PG_Signature::getOurScriptName();

		if (empty($arrRequest['pg_sig']) || !PG_Signature::check($arrRequest['pg_sig'], $thisScriptName, $arrRequest, $paymentparams->get('secret_key', '')))
			die("Wrong signature");

		if(!isset($arrRequest['pg_result'])){
			$bCheckResult = 0;
			if(empty($orderInfo) || $orderInfo->order_status != $paymentparams->get('pending_status', ''))
				$error_desc = "Товар не доступен. Либо заказа нет, либо его статус " . $arrStatuses[$orderInfo->order_status];
			elseif(sprintf('%0.2f',$arrRequest['pg_amount']) != sprintf('%0.2f',$orderInfo->order_total))
				$error_desc = "Неверная сумма";
			else
				$bCheckResult = 1;

			$arrResponse['pg_salt']              = $arrRequest['pg_salt']; // в ответе необходимо указывать тот же pg_salt, что и в запросе
			$arrResponse['pg_status']            = $bCheckResult ? 'ok' : 'error';
			$arrResponse['pg_error_description'] = $bCheckResult ?  ""  : $error_desc;
			$arrResponse['pg_sig']				 = PG_Signature::make($thisScriptName, $arrResponse, $paymentparams->get('secret_key', ''));

			$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
			$objResponse->addChild('pg_salt', $arrResponse['pg_salt']);
			$objResponse->addChild('pg_status', $arrResponse['pg_status']);
			$objResponse->addChild('pg_error_description', $arrResponse['pg_error_description']);
			$objResponse->addChild('pg_sig', $arrResponse['pg_sig']);

		}
		else{
			$bResult = 0;
			if(empty($orderInfo) ||
					(($orderInfo->order_status != $paymentparams->get('pending_status', '')) &&
					!($orderInfo->order_status == $paymentparams->get('verify_status', '') && $arrRequest['pg_result'] == 1) &&
					!($orderInfo->order_status == $paymentparams->get('invalid_status', '') && $arrRequest['pg_result'] == 0)))

				$strResponseDescription = "Товар не доступен. Либо заказа нет, либо его статус " . $arrStatuses[$orderInfo->order_status];
			elseif(sprintf('%0.2f',$arrRequest['pg_amount']) != sprintf('%0.2f',$orderInfo->order_total))
				$strResponseDescription = "Неверная сумма";
			else {
				$history = new stdClass();
				$history->amount = $arrRequest['pg_amount'];
				$history->data = 'Paybox transaction id '.$arrRequest['pg_payment_id'];

				$bResult = 1;
				$strResponseStatus = 'ok';
				$strResponseDescription = "Оплата принята";
				if ($arrRequest['pg_result'] == 1) {
					// Установим статус оплачен
					$values->order_status_code = $paymentparams->get('verify_status', '');
					$values->order_payment_status_code = 'Paid';
					$values->log = JText::_('COM_REDSHOP_ORDER_PLACED');
					$values->msg = JText::_('COM_REDSHOP_ORDER_PLACED');
				}
				else{
					// Не удачная оплата
					$values->order_status_code = $paymentparams->get('invalid_status', '');
					$values->order_payment_status_code = 'Unpaid';
					$values->log = JText::_('COM_REDSHOP_ORDER_NOT_PLACED');
					$values->msg = JText::_('COM_REDSHOP_ORDER_NOT_PLACED');
				}
			}
			if(!$bResult)
				if($arrRequest['pg_can_reject'] == 1)
					$strResponseStatus = 'rejected';
				else
					$strResponseStatus = 'error';

			$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
			$objResponse->addChild('pg_salt', $arrRequest['pg_salt']); // в ответе необходимо указывать тот же pg_salt, что и в запросе
			$objResponse->addChild('pg_status', $strResponseStatus);
			$objResponse->addChild('pg_description', $strResponseDescription);
			$objResponse->addChild('pg_sig', PG_Signature::makeXML($thisScriptName, $objResponse, $paymentparams->get('secret_key', '')));
		}
		$values->transaction_id = $arrRequest['pg_payment_id'];
		$values->order_id = $arrRequest['pg_order_id'];

		$app     = JFactory::getApplication();
		$db      = JFactory::getDbo();
		$request = JRequest::get('request');
		$Itemid  = JRequest::getVar('Itemid');
		$objOrder = new order_functions;

		$objOrder->changeorderstatus($values);;
		$objOrderDataController = new Order_detailController;
		$model = $objOrderDataController->getModel('order_detail');
		$model->resetcart();

		ob_clean();
		ob_start();

		header("Content-type: text/xml");
		echo $objResponse->asXML();
		die();
	}

	public function getparameters($payment)
	{
		$db = JFactory::getDbo();
		$sql = "SELECT * FROM #__extensions WHERE `element`='" . $payment . "'";
		$db->setQuery($sql);
		$params = $db->loadObjectList();

		return $params;
	}

	public function orderPaymentNotYetUpdated($dbConn, $order_id, $tid)
	{
		var_dump('orderPaymentNotYetUpdated');
		die();
		$db = JFactory::getDbo();
		$res = false;
		$query = "SELECT COUNT(*) `qty` FROM `#__redshop_order_payment` WHERE `order_id` = '"
			. $db->getEscaped($order_id) . "' and order_payment_trans_id = '" . $db->getEscaped($tid) . "'";
		$db->setQuery($query);
		$order_payment = $db->loadResult();

		if ($order_payment == 0)
		{
			$res = true;
		}

		return $res;
	}
}
