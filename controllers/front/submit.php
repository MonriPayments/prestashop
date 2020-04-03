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


class MonriSubmitModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {

        $inspect = $_POST;

        $customer = $this->context->customer;
        $cart = $this->context->cart;
        $mode = Configuration::get(self::KEY_MODE);
        $authenticity_token = Configuration::get($mode == self::MODE_PROD ? self::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : self::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        $merchant_key = Configuration::get($mode == self::MODE_PROD ? self::KEY_MERCHANT_KEY_PROD : self::KEY_MERCHANT_KEY_TEST);
        $form_url = $mode == self::MODE_PROD ? 'https://ipg.monri.com' : 'https://ipgtest.monri.com';
        $form_url = $this->context->link->getModuleLink($this->name, 'submit', array(), true);

        $address = new Address($cart->id_address_delivery);

        $currency = new Currency($cart->id_currency);
        $amount = "" . ((int)((double)$cart->getOrderTotal() * 100));
        $order_number = $cart->id . "_" . time();

        $inputs = [
            'utf8' =>
                [
                    'name' => 'utf8',
                    'type' => 'hidden',
                    'value' => '✓',
                ],
            'authenticity_token' =>
                [
                    'name' => 'authenticity_token',
                    'type' => 'hidden',
                    'value' => $authenticity_token
                ],
            'ch_full_name' =>
                [
                    'name' => 'ch_full_name',
                    'type' => 'hidden',
                    'value' => "{$customer->firstname} {$customer->lastname}"
                ],
            'ch_address' =>
                [
                    'name' => 'ch_address',
                    'type' => 'hidden',
                    'value' => $address->address1
                ],
            'ch_city' =>
                [
                    'name' => 'ch_city',
                    'type' => 'hidden',
                    'value' => $address->city
                ],
            'ch_zip' =>
                [
                    'name' => 'ch_zip',
                    'type' => 'hidden',
                    'value' => $address->postcode
                ],
            'ch_country' =>
                [
                    'name' => 'ch_country',
                    'type' => 'hidden',
                    'value' => $address->country
                ],
            'ch_phone' =>
                [
                    'name' => 'ch_phone',
                    'type' => 'hidden',
                    'value' => $address->phone
                ],
            'ch_email' =>
                [
                    'name' => 'ch_email',
                    'type' => 'hidden',
                    'value' => $customer->email
                ],
            'order_info' =>
                [
                    'name' => 'order_info',
                    'type' => 'hidden',
                    'value' => "Order {$cart->id}"
                ],
            'amount' =>
                [
                    'name' => 'amount',
                    'type' => 'hidden',
                    'value' => $amount
                ],
            'order_number' =>
                [
                    'name' => 'order_number',
                    'type' => 'hidden',
                    // TODO: discuss this
                    'value' => $order_number
                ],
            'currency' =>
                [
                    'name' => 'currency',
                    'type' => 'hidden',
                    'value' => $currency->iso_code,
                ],
            'transaction_type' =>
                [
                    'name' => 'transaction_type',
                    'type' => 'hidden',
                    // TODO: discuss this, how it's set?
                    'value' => 'purchase',
                ],
            'number_of_installments' =>
                [
                    'name' => 'number_of_installments',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'cc_type_for_installments' =>
                [
                    'name' => 'cc_type_for_installments',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'installments_disabled' =>
                [
                    'name' => 'installments_disabled',
                    'type' => 'hidden',
                    'value' => 'false',
                ],
            'force_cc_type' =>
                [
                    'name' => 'force_cc_type',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'moto' =>
                [
                    'name' => 'moto',
                    'type' => 'hidden',
                    'value' => 'false',
                ],
            'digest' =>
                [
                    'name' => 'digest',
                    'type' => 'hidden',
                    'value' => $this->calculateFormV2Digest($merchant_key, $order_number, $amount, $currency->iso_code),
                ],
            'language' =>
                [
                    'name' => 'language',
                    'type' => 'hidden',
                    'value' => 'en',
                ],
            'tokenize_pan_until' =>
                [
                    'name' => 'tokenize_pan_until',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'custom_params' =>
                [
                    'name' => 'custom_params',
                    'type' => 'hidden',
                    'value' => '{}',
                ],
            'tokenize_pan' =>
                [
                    'name' => 'tokenize_pan',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'tokenize_pan_offered' =>
                [
                    'name' => 'tokenize_pan_offered',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'tokenize_brands' =>
                [
                    'name' => 'tokenize_brands',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'whitelisted_pan_tokens' =>
                [
                    'name' => 'whitelisted_pan_tokens',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'custom_attributes' =>
                [
                    'name' => 'custom_attributes',
                    'type' => 'hidden',
                    'value' => '',
                ]
        ];

        echo '<pre>' . var_export($inputs, true) . '</pre>';

        $this->context->smarty->assign(['inputs' => $inputs]);

        die();

    }

    private function calculateFormV2Digest($merchant_key, $order_number, $amount, $currency)
    {
        return hash('sha512', $merchant_key . $order_number . $amount . $currency);
    }
}