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
            $posted_order_number = $transaction['order_number'] ?? null;
            $cookie_order_number = Context::getContext()->cookie->__get('order_number') ?? null;

            if (!isset($posted_order_number, $cookie_order_number) || $posted_order_number !== $cookie_order_number) {
                return $this->setErrorTemplate('Invalid order number.');
            }

            // From here on the cookie value is the only order number we act on: it was written server
            // side when the payment was authorized, so unlike the posted one it cannot be chosen by
            // whoever is sending this request.
            $order_number = $cookie_order_number;

            $cart_id = (int) (($mode === MonriConstants::MODE_TEST) ? explode('_', $order_number)[0] : $order_number);
            $comp_precision = 0;

            if (!$this->checkIfContextIsValid() || !$this->checkIfPaymentOptionIsAvailable()) {
                return $this->setErrorTemplate('Invalid payment option or invalid context.');
            }

            $monri_transaction = $this->fetchApprovedTransaction($order_number);

            if ($monri_transaction === null) {
                return $this->setErrorTemplate(
                    'Payment could not be confirmed with Monri. No order was created - if you were charged, '
                    . "please contact us quoting reference $order_number.",
                );
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
                'redirect_url',
            ];

            $extra_vars = [];

            foreach ($trx_fields as $field) {
                if (isset($monri_transaction[$field])) {
                    $extra_vars[$field] = $monri_transaction[$field];
                } elseif (isset($transaction['transaction_response'][$field])) {
                    $extra_vars[$field] = $transaction['transaction_response'][$field];
                }
            }

            // Capture, void and refund are keyed off this later, so it has to be the verified value.
            $extra_vars['transaction_id'] = $order_number;

            $currencyId = $cart->id_currency;
            $customer = new \Customer($cart->id_customer);
            $amount = (int) $monri_transaction['amount'];
            $currency = new Currency($currencyId);

            if (strcasecmp($monri_transaction['currency'], $currency->iso_code) !== 0) {
                return $this->setErrorTemplate(
                    'Paid currency and cart currency are not the same. No order was created - if you were '
                    . "charged, please contact us quoting reference $order_number.",
                );
            }

            $id_order_state = Monri::getMonriTransactionStateId();

            /*
                PaymentModule::validateOrder runs this same comparison itself, but only when the target
                state is logable - which the Authorize state is not - so on Authorize the cart can be
                changed after the payment was authorized and nothing in core notices. Deciding the state
                here rather than correcting it afterwards matters: validateOrder sends the order
                confirmation email as part of the same call, and it only skips it when the state it was
                handed is already PS_OS_ERROR. The order is still created either way, so a genuine
                payment against a changed cart leaves the merchant something to reconcile against.
             */
            $amount_mismatch = number_format($amount, $comp_precision)
                !== number_format($cart->getCartTotalPrice() * 100, $comp_precision);

            if ($amount_mismatch) {
                $id_order_state = (int) Configuration::get('PS_OS_ERROR');
            }

            $this->module->validateOrder(
                $cart->id,
                $id_order_state,
                $amount / 100,
                $this->module->displayName,
                null,
                $extra_vars,
                (int) $currencyId,
                false,
                $customer->secure_key,
            );

            if ($amount_mismatch) {
                $order = Order::getByCartId($cart->id);
                $order->note = 'Amount paid and cart amount are not the same.';
                $order->save();

                return $this->setErrorTemplate('Invalid amount.');
            }

            //Monri components has no value for number of installments in response?

            \Tools::redirect(
                $this->context->link->getPageLink(
                    'order-confirmation',
                    $this->ssl,
                    null,
                    'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key,
                ),
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage());
            $this->setErrorTemplate('Something went wrong in order creation. Please contact the administrator.');
        }
    }

    /**
     * Ask Monri whether $order_number was really paid.
     *
     * The browser posts the confirmPayment result to this controller, so every field in it - the
     * response code and the amount included - is under the shopper's control. Without this call a
     * hand-crafted POST carrying response_code 0000 and a matching amount is enough to create a paid
     * order with no money behind it. /orders/show is signed with credentials the browser never sees,
     * so its answer is the only one we act on.
     *
     * @param string $order_number
     *
     * @return array|null the trusted transaction fields, or null when Monri could not be reached or
     *                    reports the order as anything other than approved
     */
    private function fetchApprovedTransaction($order_number)
    {
        $api = new MonriApi();
        $response = $api->ordersShow($order_number);

        if ($response === false) {
            return null;
        }

        $transaction = [];

        foreach ($response->children() as $name => $value) {
            // The API answers in kebab-case (response-code), the rest of the module speaks snake_case.
            $transaction[str_replace('-', '_', $name)] = (string) $value;
        }

        $status = $transaction['status'] ?? '';
        $response_code = $transaction['response_code'] ?? '';

        if ($status !== 'approved' || $response_code !== '0000') {
            PrestaShopLogger::addLog(
                "Monri reports order $order_number as not approved - status '$status', response code '$response_code'.",
                3,
            );

            return null;
        }

        if (!isset($transaction['amount'], $transaction['currency'])) {
            PrestaShopLogger::addLog("Monri response for order $order_number is missing amount or currency.", 3);

            return null;
        }

        return $transaction;
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
