<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/


class MonriWSPaySubmitModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{

		if (!$this->checkIfContextIsValid() || !$this->checkIfPaymentOptionIsAvailable()) {
			PrestaShopLogger::addLog('Invalid payment option or invalid context.');
			$this->setTemplate('module:monri/views/templates/front/error.tpl');
			return;
		}

		$cart = $this->context->cart;
		$mode = Configuration::get(MonriConstants::KEY_MODE);
		$secret_key = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD : MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST);
		$amount = "" . ((int)((double)$cart->getOrderTotal() * 100));
		$form_url = $mode == MonriConstants::MODE_PROD ? 'https://form.wspay.biz/authorization.aspx' : 'https://formtest.wspay.biz/authorization.aspx';

		$prefix = isset($_POST['monri_module_name']) ? $_POST['monri_module_name'] : 'monri';

		$from_post = [
			'Version',
			'ShopID',
			'ShoppingCartID',
			'Lang',
			'TotalAmount',
			'ReturnUrl',
			'CancelUrl',
			'ReturnErrorURL',
			'CustomerFirstName',
			'CustomerLastName',
			'CustomerAddress',
			'CustomerCity',
			'CustomerZIP',
			'CustomerCountry',
			'CustomerPhone',
			'CustomerEmail',
		];


		$inputs = [];

		foreach ($from_post as $item) {
			$inputs[$item] = [
				'name' => $item,
				'type' => 'hidden',
				'value' => $_POST[$prefix . '_' . $item]
			];
		}

		$cart_id = $inputs['ShoppingCartID']['value'];
		$shop_id = $inputs['ShopID']['value'];

		$inputs['Signature'] = [
			'name' => 'Signature',
			'type' => 'hidden',
			'value' => $this->generateSignature($cart_id, $amount, $shop_id, $secret_key),
		];

		$this->context->smarty->assign("monri_inputs", $inputs);

		$this->context->smarty->assign('action', $form_url);

		return $this->setTemplate('module:monri/views/templates/front/submit.tpl');

	}

	/**
	 * Generate WSPay signature
	 *
	 * @return string
	 */
	private function generateSignature($cart_id, $formatted_amount, $shop_id, $secret_key)
	{
		$clean_total_amount = str_replace(',', '', $formatted_amount);
		$signature =
			$shop_id . $secret_key .
			$cart_id . $secret_key .
			$clean_total_amount . $secret_key;

		return hash('sha512', $signature);
	}

	/**
	 * Check if the context is valid
	 *
	 * @return bool
	 */
	private function checkIfContextIsValid()
	{
		return true === Validate::isLoadedObject($this->context->cart)
		       && true === Validate::isUnsignedInt($this->context->cart->id_customer)
		       && true === Validate::isUnsignedInt($this->context->cart->id_address_delivery)
		       && true === Validate::isUnsignedInt($this->context->cart->id_address_invoice);
	}

	/**
	 * Check that this payment option is still available in case the customer changed
	 * his address just before the end of the checkout process
	 *
	 * @return bool
	 */
	private function checkIfPaymentOptionIsAvailable()
	{
		$modules = Module::getPaymentModules();

		if (empty($modules)) {
			return false;
		}

		foreach ($modules as $module) {
			if (isset($module['name']) && $this->module->name === $module['name']) {
				return true;
			}
		}

		return false;
	}
}