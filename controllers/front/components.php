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


class MonriComponentsModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        try {
            PrestaShopLogger::addLog('Response data: ' . print_r($_POST, true));
            $mode = Configuration::get(MonriConstants::KEY_MODE);

            $transaction = json_decode(Tools::getValue('monri-transaction'), true);

            if (empty($transaction)) {
                return $this->setErrorTemplate('Missing Monri transaction.');
            }
            $order_number = $transaction['order_number'] ?? null;
            $cookie_order_number = Context::getContext()->cookie->__get('order_number') ?? null;

            if (!isset($order_number, $cookie_order_number) || $order_number !== $cookie_order_number) {
                return $this->setErrorTemplate('Invalid order number.');
            }

            $cart_id = (int) ( ($mode === MonriConstants::MODE_TEST) ? explode('_', $order_number)[0] : $order_number );
            $comp_precision = 0;

            if (!$this->checkIfContextIsValid() || !$this->checkIfPaymentOptionIsAvailable()) {
                return $this->setErrorTemplate('Invalid payment option or invalid context.');
            }

            $response_code = $transaction['transaction_response']['response_code'] ?? null;

            if ($response_code != '0000') {
                return $this->setErrorTemplate("Response not authorized - response code is $response_code.");
            }
            $order = Order::getByCartId($cart_id);
            if ($order) {
                return $this->setErrorTemplate('Order with this order id already exists.');
            }
            $cart = new Cart($cart_id);

            $trx_fields = [
                'id',
                'acquirer',
                'order_number',
                'amount',
                'currency',
                'outgoing_amount',
                'outgoing_currency',
                'approval_code',
                'response_code',
                'response_message',
                'reference_number',
                'systan',
                'eci',
                'cc_type',
                'status',
                'created_at',
                'transaction_type',
                'enrollment',
                'issuer',
                'three_ds_version',
                'redirect_url'
            ];

            $extra_vars = [];

            foreach ($trx_fields as $field) {
                if (isset($transaction['transaction_response'][$field])) {
                    $extra_vars[$field] = $transaction['transaction_response'][$field];
                }
            }

            if (isset($extra_vars['order_number'])) {
                $extra_vars['transaction_id'] = $extra_vars['order_number'];
            }

            $currencyId = $cart->id_currency;
            $customer = new \Customer($cart->id_customer);
            $amount = $transaction['amount'];


            // TODO: check if already approved
            $this->module->validateOrder(
                $cart->id,
                Monri::getMonriTransactionStateId(),
                $amount / 100,
                $this->module->displayName,
                null,
                $extra_vars,
                (int)$currencyId,
                false,
                $customer->secure_key
            );

            /*
                Additional check since Authorize order_status doesn't have logable flag - paid amount check in
                classes/PaymentModule.php has additional condition $order_status->logable. Since this flag is not set
                on Authorize, amount validation is skipped and cart items can be changed after gateway redirection
             */
            if ((number_format($amount, $comp_precision)) !== (number_format($cart->getCartTotalPrice() * 100, $comp_precision))) {
                $order = Order::getByCartId($cart->id);
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                $order->note = "Amount paid and cart amount are not the same.";
                $order->save();
                return $this->setErrorTemplate('Invalid amount.');
            }

            //Monri components has no value for number of installments in response?

            \Tools::redirect(
                $this->context->link->getPageLink(
                    'order-confirmation',
                    $this->ssl,
                    null,
                    'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key
                )
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage());
            $this->setErrorTemplate('Something went wrong in order creation. Please contact the administrator.');
        }
    }

    private function setErrorTemplate($message)
    {
        $this->context->smarty->assign('shopping_cart_id', Tools::getValue('order_number'));
        $this->context->smarty->assign('error_message', $message);
        PrestaShopLogger::addLog($message);
        PrestaShopLogger::addLog(json_encode(Tools::getAllValues()));
        $this->setTemplate('module:monri/views/templates/front/error.tpl');
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
