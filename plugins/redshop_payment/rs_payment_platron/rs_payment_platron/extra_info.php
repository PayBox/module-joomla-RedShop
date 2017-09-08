<?php
/**
 * @copyright Copyright (C) 2010 redCOMPONENT.com. All rights reserved.
 * @license   GNU/GPL, see license.txt or http://www.gnu.org/copyleft/gpl.html
 *            Developed by email@recomponent.com - redCOMPONENT.com
 *
 * redSHOP can be downloaded from www.redcomponent.com
 * redSHOP is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * You should have received a copy of the GNU General Public License
 * along with redSHOP; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
require_once JPATH_COMPONENT . '/helpers/helper.php';
require_once JPATH_SITE . '/administrator/components/com_redshop/helpers/redshop.cfg.php';

$objCommFunc = new order_functions;
$items = $objCommFunc->getOrderItemDetail($data['order_id']);
$strDescription = '';
foreach($items as $objItem){
	$strDescription .= $objItem->order_item_name;
	if($objItem->product_quantity > 1)
		$strDescription .= "*".$objItem->product_quantity;
	$strDescription .= "; ";
}
$returnUrl = JURI::base() . "index.php?tmpl=component&option=com_redshop&view=order_detail&controller=order_detail&task=notify_payment&payment_plugin=rs_payment_platron&Itemid=$_REQUEST[Itemid]&orderid=" . $data['order_id'];
$nLifeTime = $this->_params->get("lifetime");

$strCurrency = CURRENCY_CODE;
if($strCurrency == 'RUR')
	$strCurrency = 'RUB';

$arrFields = array(
	'pg_merchant_id'		=> $this->_params->get("merchant_id"),
	'pg_order_id'			=> $data['order_id'],
	'pg_currency'			=> $strCurrency,
	'pg_amount'				=> sprintf('%0.2f',$data['carttotal']),
	'pg_lifetime'			=> isset($nLifeTime)?$nLifeTime*60:0,
	'pg_testing_mode'		=> $this->_params->get("debug_mode"),
	'pg_description'		=> $strDescription,
	'pg_user_ip'			=> $_SERVER['REMOTE_ADDR'],
	'pg_language'			=> (JFactory::getLanguage()->getTag() == 'ru-RU')?'ru':'en',
	'pg_check_url'			=> $returnUrl,
	'pg_result_url'			=> $returnUrl,
	'pg_success_url'		=> $this->_params->get("success_url"),
	'pg_failure_url'		=> $this->_params->get("failure_url"),
	'pg_request_method'		=> 'GET',
	'pg_salt'				=> rand(21,43433), // Параметры безопасности сообщения. Необходима генерация pg_salt и подписи сообщения.
);

if(!empty($data['shippinginfo']->phone)){
	preg_match_all("/\d/", $data['billinginfo']->phone, $array);
	$strPhone = implode('',@$array[0]);
	$arrFields['pg_user_phone'] = $strPhone;
}

if(!empty($data['billinginfo']->phone)){
	preg_match_all("/\d/", $data['billinginfo']->phone, $array);
	$strPhone = implode('',@$array[0]);
	$arrFields['pg_user_phone'] = $strPhone;
}

if(!empty($data['billinginfo']->user_email)){
	$arrFields['pg_user_email'] = $data['billinginfo']->user_email;
	$arrFields['pg_user_contact_email'] = $data['billinginfo']->user_email;
}

if(!empty($data['billinginfo']->user_email)){
	$arrFields['pg_user_email'] = $data['billinginfo']->user_email;
	$arrFields['pg_user_contact_email'] = $data['billinginfo']->user_email;
}


$arrFields['pg_sig'] = PG_Signature::make('payment.php', $arrFields, $this->_params->get("secret_key"));
//var_dump($arrFields);
//var_dump($data);
//var_dump($items, $this->_params, $data, $element);
//die();

echo "<form action='https://paybox.kz/payment.php' method='post' name='platronform' id='platronform'>";
echo "<h3>Подождите...</h3>";

foreach ($arrFields as $name => $value)
{
	echo "<input type='hidden' name='$name' value='$value' />";
}

echo "<input type='submit' value='оплатить'></form>";
?>
<script type='text/javascript'>document.platronform.submit();</script>