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

function mapProductSpecificPrice($m)
{
    return $m['id_specific_price'];
}

/**
 * @since 1.5.0
 */
class MonriPriceModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        // your code here
        parent::initContent();
    }

    private function getFormattedPrice($feeAmount)
    {
        if (!$this->isTokenValid()) {
            die('Something went wrong!');
        }
        $taxConfiguration = new TaxConfiguration();
        if ($feeAmount > 0) {
            $totalOrderAmount = $this->context->cart->getOrderTotal($taxConfiguration->includeTaxes());
            $totalCartAmount = Tools::displayPrice($totalOrderAmount - $feeAmount);
            die(Tools::jsonEncode(array('amount' => $totalCartAmount)));
        } else {
            $totalOrderAmount = $this->context->cart->getOrderTotal($taxConfiguration->includeTaxes());
            die(Tools::jsonEncode(array('amount' => Tools::displayPrice($totalOrderAmount))));
        }
    }

    public function displayAjaxPrice()
    {

        try {
            $cart = $this->context->cart;
            $products = $cart->getProducts();
            $discounts = [];

            $has_monri_discount = isset($_POST['card_data']['discount']);

            $monri_discount_amount = 0;

            if ($has_monri_discount) {
                $monri_discount = $_POST['card_data']['discount'];
                $original_amount = intval($monri_discount['original_amount']);
                $amount = intval($monri_discount['amount']);
                $monri_discount_amount = ($original_amount - $amount) / $original_amount;
            } else {
                // We do not have discount so we should disable rule
                $disable_cart_rule = Monri::disableCartRule('ucbm_discount',$this->context);
            }


            // 1. fetch products
            // 2. fetch special prices
            // 3. disable discount if it's for payment method
            // 4. apply discount for product if it has monri discount enabled

            if ($has_monri_discount) {
                foreach ($products as $product) {
                    $specific_prices_discount = null;
                    $id_specific_price = $product['specific_prices']['id_specific_price'];
                    $specific_prices_discount = self::getSpecificPriceDetails($id_specific_price, $monri_discount_amount);
                    if ($specific_prices_discount == null) {
                        continue;
                    }

                    $mpc_price = $product['price_without_reduction'];
                    $price_with_discount = null;

                    $price_with_discount = $mpc_price * (1 - $specific_prices_discount['discount']);
                    $discounts[] = [
                        // Price without VAT
                        'price' => $product['price'],
                        'price_with_discount' => $price_with_discount,
                        'discount_amount' => $mpc_price - $price_with_discount,
                        "total_wt" => $product["total_wt"],
                        // Price with VAT
                        "price_wt" => $product["price_wt"],
                        'has_discount' => $product['price_wt'] != $product['price_without_reduction'],
                        // MPC with VAT
                        'mpc' => $product['price_without_reduction'],
                        'specific_prices' => $product['specific_prices'],
                        'specific_prices_discount' => $specific_prices_discount
                    ];
                }
            }

            $monri_discount_sum = 0;

            foreach ($discounts as $item) {
                $monri_discount_sum = $monri_discount_sum + $item['discount_amount'];
            }

            $add_cart_rule = null;
            if ($monri_discount_sum > 0) {
                Monri::addCartRule('ucbm_discount','Unicredit popust', $this->context, $monri_discount_sum);
            } else {
                Monri::disableCartRule('ucbm_discount',$this->context);
            }

            die(Tools::jsonEncode([
                'price' => $this->getFormattedPrice($monri_discount_sum)
            ]));
        } catch (Exception $exception) {
            die(Tools::jsonEncode(['error' => $exception]));
        }
    }

    static function getSpecificPriceDetails($id, $discount)
    {
        if (!$id) {
            return null;
        }

        $apiKey = Monri::getPrestashopWebServiceApiKey();
        $authorizationKey = base64_encode($apiKey . ':');
        $url = Monri::baseShopUrl() . "/api/specific_prices/$id?output_format=JSON";
        $specific_prices_api_response = Monri::curlGetJSON($url, array("Authorization: Basic $authorizationKey"));
        $specific_prices_response = $specific_prices_api_response['response']['specific_price'];
        $id_specific_price_rule = $specific_prices_response['id_specific_price_rule'];
        $specific_price_rule = self::getSpecificPriceRule($id_specific_price_rule);

        if ($specific_price_rule == null) {
            return null;
        }

        return [
            'id' => $id,
            'specific_price_rule' => $specific_price_rule,
            'apply_monri_discount' => $specific_price_rule != null,
            'discount' => $discount
        ];
    }

    static function getSpecificPriceRule($id)
    {
        if (!$id) {
            return null;
        }

        $apiKey = Monri::getPrestashopWebServiceApiKey();
        $authorizationKey = base64_encode($apiKey . ':');
        $url = Monri::baseShopUrl() . "/api/specific_price_rules/$id?output_format=JSON";
        $rv = Monri::curlGetJSON($url, array("Authorization: Basic $authorizationKey"));
        if (isset($rv['response']['specific_price_rule'])) {
            $specific_price_rule = $rv['response']['specific_price_rule'];
            if (strpos($specific_price_rule['name'], 'UCB') === 0) {
                return $specific_price_rule;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
}
