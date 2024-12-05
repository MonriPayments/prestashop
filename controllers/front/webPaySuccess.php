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


class MonriwebPaySuccessModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        try {
            PrestaShopLogger::addLog('Response data: ' . print_r($_GET, true));
            $mode = Configuration::get(MonriConstants::KEY_MODE);
            $response_code = Tools::getValue('response_code');
            $cart_id = ($mode === MonriConstants::MODE_TEST) ? explode('_', Tools::getValue('order_number'), 2) : Tools::getValue('order_number');
            $comp_precision = 0;

            if (!$this->checkIfContextIsValid() || !$this->checkIfPaymentOptionIsAvailable()) {
                return $this->setErrorTemplate('Invalid payment option or invalid context.');
            }
            if (!$this->validateReturn()) {
                return $this->setErrorTemplate('Failed to validate response.');
            }
            if ($response_code != '0000') {
                return $this->setErrorTemplate("Response not authorized - response code is $response_code.");
            }
            $cart_id = (int) $cart_id[0];
            $order = Order::getByCartId($cart_id);
            if ($order) {
                return $this->setErrorTemplate('Order with this order id already exists.');
            }
            $cart = new Cart($cart_id);

            $trx_fields = ['acquirer',
                'amount',
                'approval_code',
                'authentication',
                'cc_type',
                'ch_full_name',
                'currency',
                'custom_params',
                'enrollment',
                'issuer',
                'language',
                'masked_pan',
                'number_of_installments',
                'order_number',
                'response_code',
                'digest',
                'pan_token',
                'original_amount'
            ];

            $extra_vars = [];

            foreach ($trx_fields as $field) {
                if (Tools::getValue($field)) {
                    $extra_vars[$field] = Tools::getValue($field);
                }
            }

            if (isset($extra_vars['order_number'])) {
                $extra_vars['transaction_id'] = $extra_vars['order_number'];
            }

            $currencyId = $cart->id_currency;
            $customer = new \Customer($cart->id_customer);
            $amount = intval(Tools::getValue('amount'));

            if (Tools::getValue('original_amount')) {
                $this->applyDiscount($cart, $amount, intval(Tools::getValue('original_amount')));
            }

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

    private function applyDiscount($cart, $amount, $original_amount)
    {

        $cart_rule = new CartRule();
        $language_ids = LanguageCore::getIDs(false);

        foreach ($language_ids as $language_id) {
            $cart_rule->name[$language_id] = $this->trans('Unicredit Akcija');
            $cart_rule->description = $this->trans('Unicredit akcija - popust 15%');
        }

        $now = time();
        $cart_rule->date_from = date('Y-m-d H:i:s', $now);
        $cart_rule->date_to = date('Y-m-d H:i:s', strtotime('+10 minute'));
        $cart_rule->highlight = false;
        $cart_rule->partial_use = false;
        $cart_rule->active = true;
        $cart_rule->id_customer = $cart->id_customer;
        $cart_rule->reduction_amount = ($original_amount - $amount) / 100;
        $cart_rule->add();
        $cart->addCartRule($cart_rule->id);
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

    /**
     * Check if WebPay response is valid
     *
     * @return bool
     */
    private function validateReturn()
    {

        if (!Tools::getValue('digest')  || ! preg_match('/^[a-f0-9]{128}$/', Tools::getValue('digest'))) {
            return false;
        }
        $merchant_key = Monri::getMonriWebPayMerchantKey();
        $digest = $this->sanitizeHash(Tools::getValue('digest'));

        $calculated_url = Tools::getCurrentUrl();
        $calculated_url = strtok($calculated_url, '?');
        $arr = explode('?', $_SERVER['REQUEST_URI']);

        // If there's more than one '?' shift and join with ?, it's special case of having '?' in success url
        // eg https://test.com/?page_id=6order-recieved?
        if (count($arr) > 2) {
            array_shift($arr);
            $query_string = implode('?', $arr);
        } else {
            $query_string = end($arr);
        }

        $calculated_url .= '?' . $query_string;
        $calculated_url = preg_replace('/&digest=[^&]*/', '', $calculated_url);

        //generate known digest
        $check_digest = hash('sha512', $merchant_key . $calculated_url);

        return hash_equals($check_digest, $digest);
    }

    /**
     * Sanitize hash, only hex digits/letters allowed in lowercase (0-9 and a-f)
     *
     * @param string $hash
     *
     * @return string
     */
    public static function sanitizeHash($hash)
    {
        return (string) preg_replace('/[^a-f0-9]/', '', $hash);
    }
}
