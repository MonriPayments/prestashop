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


class MonriCheckModuleFrontController extends ModuleFrontController
{

    private function approvedOrder($cart, $trx_response)
    {
        $customer = new \Customer($cart->id_customer);
        $amount = intval($_POST['monri-amount']);
        $currencyId = $cart->id_currency;
        $extra_vars = [];

        foreach ($trx_response as $field) {
            $extra_vars[] = $field;
        }

        if (isset($extra_vars['order_number'])) {
            $extra_vars['transaction_id'] = $extra_vars['order_number'];
        }
        $this->module->validateOrder(
            $cart->id, 2, $amount / 100, $this->module->displayName, null, [],
            (int)$currencyId, false, $customer->secure_key
        );

        \Tools::redirect(
            $this->context->link->getPageLink(
                'order-confirmation', $this->ssl, null,
                'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key
            )
        );
    }

    private function declinedOrder($card, $trx_response)
    {
        $this->setTemplate('module:monri/views/templates/front/error.tpl');
    }

    private function checkOrder($check_count)
    {

        if ($check_count > 1 && $check_count < 4) {
            sleep(1);
        }

        if ($check_count > 3) {
            return $this->declinedOrder($this->cart, []);
        } else {
            $xml = "<order>
   <order-number>$this->order_number</order-number>
   <authenticity-token>$this->authenticity_token</authenticity-token>
   <digest>$this->digest</digest>
</order>
";
            $response = Monri::curlPostXml("$this->base_url/orders/show", $xml);

            if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
                $responseAsArray = Monri::xmlToArray($response['response']);
                $status = $responseAsArray['status'];
                $code = $responseAsArray['response-code'];

                if ($status === 'approved' && $code === '0000') {
                    return $this->approvedOrder($this->cart, $responseAsArray);
                } else {
                    return $this->declinedOrder($this->cart, $responseAsArray);
                }
            } else {
                return $this->checkOrder($this->retry_count++);
            }
        }
    }

    public function postProcess()
    {
        $this->retry_count = 0;
        $this->order_number = $_POST['monri-order-number'];
        $this->parts = explode('_', $this->order_number, 2);
        $this->merchant_key = Monri::getMerchantKey();
        $this->authenticity_token = Monri::getAuthenticityToken();
        $this->cart = new Cart($this->parts[0]);
        $this->digest = hash('sha1', $this->merchant_key . $this->order_number);
        $this->base_url = Monri::baseUrl();
        return $this->checkOrder($this->retry_count);
    }

}