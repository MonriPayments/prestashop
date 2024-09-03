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


class MonriWSPaySuccessModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		try {
			//todo: sanitize. Does Presta shop have it?
			PrestaShopLogger::addLog("Response data: " . ( print_r( $_GET, true ) ));
			$success       = ( isset( $_GET['Success'] ) && $_GET['Success'] === '1' ) ? '1' : '0';
			$approval_code = ! empty( $_GET['ApprovalCode'] ) ? $_GET['ApprovalCode'] : '';
			$trx_authorized = ( $success === '1' ) && ! empty( $approval_code );

			if (!$this->checkIfContextIsValid() || !$this->checkIfPaymentOptionIsAvailable()) {
				PrestaShopLogger::addLog('Invalid payment option or invalid context.');
				$this->setTemplate('module:monri/views/templates/front/error.tpl');
				return;
			}

			if ( ! isset( $_GET['ShoppingCartID'] ) ) {
				PrestaShopLogger::addLog('Shopping cart ID is missing.');
				$this->setTemplate('module:monri/views/templates/front/error.tpl');
				return;
			}
			$cart_id = explode('_', $_GET['ShoppingCartID'], 2);

			if ( empty($cart_id) ) {
				PrestaShopLogger::addLog('Invalid shopping cart ID.');
				$this->setTemplate('module:monri/views/templates/front/error.tpl');
				return;
			}

			if ( !$this->validateReturn() || !$trx_authorized ) {
				PrestaShopLogger::addLog('Failed to validate response.');
				$this->setTemplate('module:monri/views/templates/front/error.tpl');
				return;
			}

			$cart_id = (int) $cart_id[0];
			$order = Order::getByCartId($cart_id);
			if($order) {
				PrestaShopLogger::addLog('Order with this order id already exists.');
				$this->setTemplate('module:monri/views/templates/front/error.tpl');
				return;
			}
			$cart = new Cart($cart_id);

			$trx_fields = [
				'CustomerFirstname',
				'CustomerSurname',
				'CustomerAddress',
				'CustomerCountry',
				'CustmerZIP',
				'CustomerCity',
				'CustomerEmail',
				'CustomerPhone',
				'ShoppingCartID',
				'Lang',
				'DateTime',
				'Amount',
				'ECI',
				'STAN',
				'WsPayOrderId',
				'PaymentType',
				'CreditCardNumber',
				'PaymentPlan',
				'Success',
				'ApprovalCode',
				'ErrorMessage'
			];


			$extra_vars = [];

			foreach ($trx_fields as $field) {
				if(isset($_GET[$field])) {
					$extra_vars[$field] = $_GET[$field];
				}
			}

			if (isset($extra_vars['WsPayOrderId'])) {
				$extra_vars['transaction_id'] = $extra_vars['WsPayOrderId'];
			}

			$currencyId = $cart->id_currency;
			$customer = new \Customer($cart->id_customer);
			$amount = (float) str_replace(",", ".", $_GET['Amount']);
			// todo: check what happens with promo codes

			// Presta shop creates order only on success redirect
			$this->module->validateOrder(
				$cart->id, 2, $amount, $this->module->displayName, null, $extra_vars,
				(int)$currencyId, false, $customer->secure_key
			);

			\Tools::redirect(
				$this->context->link->getPageLink(
					'order-confirmation', $this->ssl, null,
					'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key
				)
			);
		}
		catch (Exception $e) {
			PrestaShopLogger::addLog($e->getMessage());
			$this->setTemplate('module:monri/views/templates/front/error.tpl');
		}

	}
	/**
	 * Check if WSPay response is valid
	 *
	 * @return bool
	 */
	private function validateReturn() {

		if ( ! isset( $_GET['ShoppingCartID'], $_GET['Signature'] ) ) {
			return false;
		}
		//todo: sanitize response data
		$order_id      = ( $_GET['ShoppingCartID'] );
		$digest        = $_GET['Signature'];
		$success       = ( isset( $_GET['Success'] ) && $_GET['Success'] === '1' ) ? '1' : '0';
		$approval_code = isset( $_GET['ApprovalCode'] ) ? $_GET['ApprovalCode'] : '';


		$mode = Configuration::get(MonriConstants::KEY_MODE);
		$shop_id = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_WSPAY_SHOP_ID_PROD : MonriConstants::MONRI_WSPAY_SHOP_ID_TEST);
		$secret_key = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD : MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST);

		$digest_parts = array(
			$shop_id,
			$secret_key,
			$order_id,
			$secret_key,
			$success,
			$secret_key,
			$approval_code,
			$secret_key,
		);
		$check_digest = hash( 'sha512', implode( '', $digest_parts ) );

		return hash_equals( $check_digest, $digest );
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