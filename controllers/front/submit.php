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
        $cart = $this->context->cart;

        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $merchant_key = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
        $amount = "" . ((int)((double)$cart->getOrderTotal() * 100));
        $form_url = $mode == MonriConstants::MODE_PROD ? 'https://ipg.monri.com' : 'https://ipgtest.monri.com';

        $prefix = isset($_POST['monri_module_name']) ? $_POST['monri_module_name'] : 'monri';

        $from_post = [
            'utf8',
            'authenticity_token',
            'ch_full_name',
            'ch_address',
            'ch_city',
            'ch_zip',
            'ch_country',
            'ch_phone',
            'ch_email',
            'order_info',
            'order_number',
            'currency',
            'transaction_type',
            'number_of_installments',
            'cc_type_for_installments',
            'installments_disabled',
            'force_cc_type',
            'moto',
            'language',
            'tokenize_pan_until',
            'custom_params',
            'tokenize_pan',
            'tokenize_pan_offered',
            'tokenize_brands',
            'whitelisted_pan_tokens',
            'custom_attributes',
        ];

        $inputs = [];

        foreach ($from_post as $item) {
            $inputs[$item] = [
                'name' => $item,
                'type' => 'hidden',
                'value' => $_POST[$prefix . '_' . $item]
            ];
        }

//        echo '<pre>' . var_export($inputs, true) . '</pre>';
//        die();

        $inputs['amount'] = [
            'name' => 'amount',
            'type' => 'hidden',
            'value' => $amount
        ];

        $order_number = $inputs['order_number']['value'];

        $inputs['digest'] = [
            'name' => 'digest',
            'type' => 'hidden',
            'value' => $this->calculateFormV2Digest($merchant_key, $order_number, $amount, $inputs['currency']['value']),
        ];

        $this->context->smarty->assign('monri_inputs', $inputs);
        $this->context->smarty->assign('action', "$form_url/v2/form");

        return $this->setTemplate('submit.tpl');
    }

    private function calculateFormV2Digest($merchant_key, $order_number, $amount, $currency)
    {
//        $d = $merchant_key . $order_number . $amount . $currency;
//        var_dump($d);
//        die();
        return hash('sha512', $merchant_key . $order_number . $amount . $currency);
    }
}