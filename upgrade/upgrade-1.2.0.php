<?php
/**
 * File: /upgrade/upgrade-1.2.0.php
 */
function upgrade_module_1_2_0($module) {
	// Process Module upgrade to 1.2.0
	// ....

	$transactionTypeValue = (string) Tools::getValue('MONRI_TRANSACTION_TYPE');
	$paymentGatewayServiceTypeValue = (string) Tools::getValue('MONRI_PAYMENT_GATEWAY_SERVICE_TYPE');
	if(empty($transactionTypeValue)) {
		Configuration::updateValue('MONRI_TRANSACTION_TYPE', MonriConstants::TRANSACTION_TYPE_CAPTURE);
	}
	if(empty($paymentGatewayServiceTypeValue)) {
		Configuration::updateValue('MONRI_PAYMENT_GATEWAY_SERVICE_TYPE', MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY);
	}
	return true; // Return true if success.
}
