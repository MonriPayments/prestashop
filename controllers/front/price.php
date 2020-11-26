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

    const CREDIT_CARD_PAYMENT_DISCOUNT = 0.10;

    private function applyVAT($amount)
    {
        return $amount;
    }

    private function getPriceForDiscount($product)
    {
        return $product['price_without_reduction'];
    }

    public function displayAjaxPrice()
    {

        if (!isset($_POST['client_secret'])) {
            die("Unexpected error occurred! Missing clientSecret");
        }

        try {
            $cart = $this->context->cart;
            $client_secret = $_POST['client_secret'];
            Monri::disableCartRule('ucbm_discount', $this->context);
            $products = $cart->getProducts();
            $discounts = [];

            $has_monri_discount = isset($_POST['card_data']['discount']);

            $monri_discount_percentage = 0;

            if ($has_monri_discount) {
                $monri_discount = $_POST['card_data']['discount'];
                $original_amount = intval($monri_discount['original_amount']);
                $amount = intval($monri_discount['amount']);
                $monri_discount_percentage = ($original_amount - $amount) / $original_amount;
            } else {
                // We do not have discount so we should disable rule

            }


            // 1. fetch products
            // 2. fetch special prices
            // 3. disable discount if it's for payment method
            // 4. apply discount for product if it has monri discount enabled
            foreach ($products as $product) {
                $id_specific_price = $product['specific_prices']['id_specific_price'];
                $discount = self::getMonriDiscount($id_specific_price, $monri_discount_percentage);
                $price_for_discount = self::getPriceForDiscount($product);
                $price_with_discount = $price_for_discount * $discount;
                $discounts[] = [
                    'discount_percentage' => $discount,
                    'price_for_discount' => self::getPriceForDiscount($product),
                    // Price without VAT
                    'price' => $product['price'],
                    'price_without_reduction_without_tax' => $product['price_without_reduction_without_tax'],
                    'discount_amount' => $price_with_discount,
                    "total_wt" => $product["total_wt"],
                    // Price with VAT
                    "price_wt" => $product["price_wt"],
                    'has_discount' => $product['total'] != $product['price_without_reduction_without_tax'],
                    // MPC with VAT
                    'mpc' => $product['price_without_reduction'],
                    'specific_prices' => $product['specific_prices'],
                    'product' => $product,
                    'discount' => $discount
                ];
            }

            $monri_discount_sum = 0;

            foreach ($discounts as $item) {
                $monri_discount_sum = $monri_discount_sum + $item['discount_amount'];
            }

            $monri_discount_sum = $this->applyVAT($monri_discount_sum);
            if ($monri_discount_sum > 0) {
                Monri::addCartRule('ucbm_discount', 'Unicredit popust', $this->context, $monri_discount_sum);
            } else {
                Monri::disableCartRule('ucbm_discount', $this->context);
            }
            $taxConfiguration = new TaxConfiguration();
            $totalOrderAmount = $this->context->cart->getOrderTotal($taxConfiguration->includeTaxes());
            // update amount
            Monri::updatePayment($client_secret, intval(($totalOrderAmount * 100)));
            $rv = [
                'amount' => Tools::displayPrice($totalOrderAmount),
                'discounts' => $discounts
            ];
            die(Tools::jsonEncode($rv));
        } catch (Exception $exception) {
            die(Tools::jsonEncode(['error' => $exception]));
        }
    }

    static function getMonriDiscount($id, $discount)
    {
        if (!$id) {
            return self::CREDIT_CARD_PAYMENT_DISCOUNT;
        }

        $apiKey = Monri::getPrestashopWebServiceApiKey();
        $authorizationKey = base64_encode($apiKey . ':');
        $url = Monri::baseShopUrl() . "/api/specific_prices/$id?output_format=JSON";
        $specific_prices_api_response = Monri::curlGetJSON($url, array("Authorization: Basic $authorizationKey"));
        $specific_prices_response = $specific_prices_api_response['response']['specific_price'];
        $id_specific_price_rule = $specific_prices_response['id_specific_price_rule'];
        $specific_price_rule = self::getSpecificPriceRule($id_specific_price_rule);

        if ($specific_price_rule == null) {
            return self::CREDIT_CARD_PAYMENT_DISCOUNT;
        }

        return $discount;
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
