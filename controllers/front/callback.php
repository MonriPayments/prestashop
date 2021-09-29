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

/**
 * @since 1.5.0
 */
class MonriCallbackModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        define('MONRI_CALLBACK_IMPL', true);
        require_once 'callback-url.php';

        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $merchant_key = Configuration::get(
            $mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST
        );

        $directory = dirname(dirname(dirname($_SERVER['REQUEST_URI'])));
        $pathname = $directory . '/module/monri/callback';

        monri_handle_callback($pathname, $merchant_key, function($payload) {
            $order_number = $payload['order_number'];
            $cart = new Cart($order_number);

            $callback_fields = [
                'acquirer',
                'amount',
                'approval_code',
                'authentication',
                'cc_type',
                'ch_full_name',
                'currency',
                'custom_params',
                'enrollment',
                'issuer',
                'masked_pan',
                'number_of_installments',
                'order_number',
                'response_code',
                'pan_token',
            ];

            $additional_fields = [];

            foreach ($callback_fields as $field) {
                if(isset($payload[$field])) {
                    $additional_fields[] = $payload[$field];
                }
            }

            if (isset($additional_fields['order_number'])) {
                $additional_fields['transaction_id'] = $additional_fields['order_number'];
            }

            $currency_id = $cart->id_currency;
            $customer = new \Customer($cart->id_customer);
            $amount = (int) $payload['amount'];

            // TODO: check if already approved
            $this->module->validateOrder(
                $cart->id, 2, $amount/100, $this->module->displayName, null,
                $additional_fields, $currency_id, false, $customer->secure_key
            );
        });
    }
}
