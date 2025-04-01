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
            PrestaShopLogger::addLog('Response data: ' . print_r($_GET, true));
            $success = (Tools::getValue('Success') && Tools::getValue('Success') === '1') ? '1' : '0';
            $approval_code = Tools::getValue('ApprovalCode', '');
            $trx_authorized = ($success === '1') && !empty($approval_code);
            $mode = Configuration::get(MonriConstants::KEY_MODE);
            $comp_precision = 2;

            if (!$this->checkIfContextIsValid() || !$this->checkIfPaymentOptionIsAvailable()) {
                return $this->setErrorTemplate('Invalid payment option or invalid context.');
            }

            if (!Tools::getValue('ShoppingCartID')) {
                return $this->setErrorTemplate('Shopping cart ID is missing.');
            }
	        $order_number = Tools::getValue('ShoppingCartID');
	        $cart_id = (int) ( ($mode === MonriConstants::MODE_TEST) ? explode('_', $order_number)[0] : $order_number );

            if (empty($cart_id)) {
                return $this->setErrorTemplate('Invalid shopping cart ID.');
            }

            if (!$this->validateReturn() || !$trx_authorized) {
                return $this->setErrorTemplate('Failed to validate response.');
            }

            $order = Order::getByCartId($cart_id);
            if ($order) {
                return $this->setErrorTemplate('Order with this order id already exists.');
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
                'ErrorMessage',
            ];

            $extra_vars = [];

            foreach ($trx_fields as $field) {
                if (Tools::getValue($field)) {
                    $extra_vars[$field] = Tools::getValue($field);
                }
            }

            if (isset($extra_vars['WsPayOrderId'])) {
                $extra_vars['transaction_id'] = $extra_vars['WsPayOrderId'];
            }

            $currency_id = $cart->id_currency;
            $customer = new Customer($cart->id_customer);
            $amount = (float) str_replace(',', '.', Tools::getValue('Amount'));
            $id_order_state = Monri::getMonriTransactionStateId();

            // Presta shop creates order only on success redirect
            $this->module->validateOrder(
                $cart->id,
                $id_order_state,
                $amount,
                $this->module->displayName,
                null,
                $extra_vars,
                $currency_id,
                false,
                $customer->secure_key
            );


            /*
                Additional check since Authorize order_status doesn't have logable flag - paid amount check in
                classes/PaymentModule.php has additional condition $order_status->logable. Since this flag is not set
                on Authorize, amount validation is skipped and cart items can be changed after gateway redirection
             */
            if ((number_format($amount, $comp_precision)) !== (number_format($cart->getCartTotalPrice(), $comp_precision))) {
                $order = Order::getByCartId($cart_id);
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                $order->note = "Amount paid and cart amount are not the same.";
                $order->save();
                return $this->setErrorTemplate('Invalid amount.');
            }

            Tools::redirect(
                $this->context->link->getPageLink(
                    'order-confirmation',
                    $this->ssl,
                    null,
                    'id_cart=' . $cart->id .
                    '&id_module=' . $this->module->id .
                    '&id_order=' . $this->module->currentOrder .
                    '&key=' . $customer->secure_key
                )
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage());
            $this->setErrorTemplate('Something went wrong in order creation. Please contact the administrator.');
        }
    }

    /**
     * Check if WSPay response is valid
     *
     * @return bool
     */
    private function validateReturn()
    {
        if (!Tools::getValue('Success') || !Tools::getValue('ShoppingCartID')) {
            return false;
        }
        $order_id = Tools::getValue('ShoppingCartID');
        $digest = Tools::getValue('Signature');
        $success = (Tools::getValue('Success') && Tools::getValue('Success') === '1') ? '1' : '0';
        $approval_code = Tools::getValue('ApprovalCode') ? Tools::getValue('ApprovalCode') : '';

        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $shop_id = Configuration::get(
            $mode == MonriConstants::MODE_PROD ?
                MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST
        );
        $secret_key = Configuration::get(
            $mode == MonriConstants::MODE_PROD ?
                MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST
        );

        $digest_parts = [
            $shop_id,
            $secret_key,
            $order_id,
            $secret_key,
            $success,
            $secret_key,
            $approval_code,
            $secret_key,
        ];
        $check_digest = hash('sha512', implode('', $digest_parts));

        return hash_equals($check_digest, $digest);
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

    private function setErrorTemplate($message)
    {
        $this->context->smarty->assign('shopping_cart_id', Tools::getValue('ShoppingCartID'));
        $this->context->smarty->assign('error_message', $message);
        PrestaShopLogger::addLog($message);
        $this->setTemplate('module:monri/views/templates/front/error.tpl');
    }
}
