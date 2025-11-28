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


class MonriCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendHttpResponse(400, 'Bad Request', 'Invalid request method.');
        }

        if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->sendHttpResponse(400, 'Bad Request', 'Authorization header missing.');
        }
        $merchant_key = Monri::getMonriWebPayMerchantKey();

        if (empty($merchant_key)) {
            $this->sendHttpResponse(400, 'Bad Request', 'Missing merchant key.');
        }
        $authorization = trim(str_replace('WP3-callback', '', $_SERVER['HTTP_AUTHORIZATION']));

        $json = file_get_contents('php://input');
        PrestaShopLogger::addLog('Monri callback request: ' . print_r($json, true));

        // Calculating the digest...
        $digest = hash('sha512', $merchant_key . $json);

        // ... and comparing it with one from the headers.
        if ($digest !== $authorization) {
            $this->sendHttpResponse(400, 'Bad Request', 'Invalid Authorization header.');
        }

        try {
            $payload = json_decode($json, true);
        } catch (\Throwable $e) {
            $this->sendHttpResponse(400, 'Bad Request', 'Invalid request content');
        }

        if (! isset($payload['order_number']) || ! isset($payload['status'])) {
            $this->sendHttpResponse(400, 'Bad Request', 'Order information not found in request content.');
        }

        $order_number = $payload['order_number'];
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $cart_id = (int) ( ($mode === MonriConstants::MODE_TEST) ? explode('_', $order_number)[0] : $order_number );
        $order = Order::getByCartId($cart_id);

        //order already exists and has correct status
        if ($order && $order->getCurrentState() === Monri::getMonriTransactionStateId()) {
            $comp_precision = 0;
            if ((number_format($payload['amount'], $comp_precision)) !== (number_format($order->getOrdersTotalPaid() * 100, $comp_precision))) {
                $this->sendHttpResponse(400, 'Bad Request', 'Invalid amount.');
            }
            $this->sendHttpResponse(200, 'OK', 'Order updated successfully.');
        }

        //order has not been created yet
        if (!$order) {
            $this->createOrder($cart_id, $payload);
            $this->sendHttpResponse(200, 'OK', 'Order updated successfully.');
        }

        //order exists but has incorrect status
        $order->setCurrentState(Monri::getMonriTransactionStateId());
        $this->sendHttpResponse(200, 'OK', 'Order updated successfully.');
    }

    private function sendHttpResponse($code, $status_message, $body_message)
    {
        header($_SERVER['SERVER_PROTOCOL'] . " $code $status_message", true, $code);
        echo $body_message;
        exit;
    }

    private function createOrder($cart_id, $body_payload)
    {
        //callback creaters order
        $cart = new Cart($cart_id);
        $comp_precision = 0;

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
            'original_amount',
            'number_of_installments'
        ];

        $extra_vars = [];

        foreach ($trx_fields as $field) {
            if (!empty($body_payload[$field])) {
                $extra_vars[$field] = $body_payload[$field];
            }
        }

        if (isset($extra_vars['order_number'])) {
            $extra_vars['transaction_id'] = $extra_vars['order_number'];
        }

        $amount = $extra_vars['amount'];

        $this->module->validateOrder(
            $cart->id,
            Monri::getMonriTransactionStateId(),
            $amount / 100,
            $this->module->displayName,
            null,
            $extra_vars,
            $cart->id_currency,
            false
        );

        /*
            Additional check since Authorize order_status doesn't have logable flag - paid amount check in
            classes/PaymentModule.php has additional condition $order_status->logable. Since this flag is not set
            on Authorize, amount validation is skipped and cart items can be changed after gateway redirection
         */
        if ((number_format($body_payload['amount'], $comp_precision)) !== (number_format($cart->getCartTotalPrice() * 100, $comp_precision))) {
            $order = Order::getByCartId($cart->id);
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $order->note = "Amount paid and cart amount are not the same.";
            $order->save();
            $this->sendHttpResponse(400, 'Bad Request', 'Invalid amount.');
        }

        if (isset($extra_vars['number_of_installments'])) {
            $order = Order::getByCartId($cart->id);
            $order->note = $this->l('Number of installments: ') . $extra_vars['number_of_installments'];
            $order->save();
        }
    }
}
