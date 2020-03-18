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


class MonriSuccessModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $digest = $_GET['digest'];
        $protocol = self::resolveProtocol();
        $url = $protocol . $_SERVER['SERVER_NAME'] . self::resolvePort() . dirname($_SERVER['REQUEST_URI']) . '/success';
        $full_url = $url . '?' . $_SERVER['QUERY_STRING'];
        $url_parsed = parse_url(preg_replace('/&digest=[^&]*/', '', $full_url));
        $calculated_url = $url_parsed['scheme'] . '://' . $url_parsed['host'] . ($url_parsed['port'] == '' ? '' : ":" . $url_parsed['port']) . $url_parsed['path'] . '?' . $url_parsed['query'];
        $merchant_key = Monri::getMerchantKey();
        $checkdigest = hash('sha512', $merchant_key . $calculated_url);

        $cart = new Cart($_GET['order_number']);
        if ($checkdigest != $digest) {
            $this->setTemplate('module:monri/views/templates/front/error.tpl');
        } else {
            $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
            $extra_vars = array();
            $currencyId = $cart->id_currency;
            $customer = new \Customer($cart->id_customer);
            // TODO: check if already approved
            $this->module->validateOrder(
                $cart->id, 2, $total, $this->module->displayName, null, $extra_vars,
                (int)$currencyId, false, $customer->secure_key
            );
            \Tools::redirect(
                $this->context->link->getPageLink(
                    'order-confirmation', $this->ssl, null,
                    'id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key
                )
            );
        }

//        $inspect = [
//            'merchant_key' => $merchant_key,
//            'check_digest' => $checkdigest,
//            'digest' => $digest,
//            'same_digest' => $checkdigest == $digest,
//            'full_url' => $full_url,
//            'url' => $url,
//            'port' => $_SERVER['SERVER_PORT'],
//            'server_name' => $_SERVER['SERVER_NAME'],
//            'request_uri' => $_SERVER['REQUEST_URI'],
//            'calculated_url' => $calculated_url,
//            'self' => $_SERVER['PHP_SELF'],
//            'url_parsed' => $url_parsed
//        ];
//        echo '<pre>' . var_export($inspect, true) . '</pre>';
//        die();
//        $cart = $this->context->cart;
    }

    private function resolveProtocol()
    {
        if (isset($_SERVER['HTTPS']) &&
            ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            return 'https://';
        } else {
            return 'http://';
        }
    }

    private function resolvePort()
    {
        $port = $_SERVER['SERVER_PORT'];
        if ($port === '') {
            return '';
        } else {
            return ":$port";
        }
    }
}