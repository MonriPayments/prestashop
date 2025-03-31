<?php
/**
 * File: /upgrade/upgrade-1.4.0.php
 */
function upgrade_module_1_4_0($module) {
	// Process Module upgrade to 1.4.0
	// ....
	$allowPayingInInstallments = (string) Tools::getValue('MONRI_INSTALLMENTS');
	if(empty($allowPayingInInstallments)) {
		Configuration::updateValue('MONRI_INSTALLMENTS', MonriConstants::MONRI_INSTALLMENTS_NO);
	}
	return true; // Return true if success.
}
