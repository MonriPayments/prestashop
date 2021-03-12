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
class MonriPriceModuleFrontController extends ModuleFrontController
{
    /** @noinspection PhpUnused */
    public function initContent()
    {
        $this->ajax = true;
        // your code here
        parent::initContent();
    }

    private function getPriceForDiscount($product)
    {
        return $product['price_without_reduction'];
    }

    /** @noinspection PhpUnused */
    public function displayAjaxUpdate()
    {
        $this->calculatePrice(true);
    }

    private function calculatePrice($updatePrice)
    {
        if (!isset($_POST['client_secret'])) {
            die("Unexpected error occurred! Missing clientSecret");
        }

        $total_price_with_discounts = 0;

        try {
            $cart = $this->context->cart;
            $client_secret = $_POST['client_secret'];
            Monri::disableCartRule('monri_special_discount', $this->context);
            $products = $cart->getProducts();

            $custom_params_products = [];

            // 1. fetch products
            // 2. fetch special prices
            // 3. disable discount if it's for payment method
            // 4. apply discount for product if it has monri discount enabled
            foreach ($products as $product) {
                $discount_result = self::getSpecialPriceDiscount($product, [
                    new MonriDiscount(isset($_POST['card_data']) ? $_POST['card_data'] : []),
                    new MonriCardDiscount(0.19, '2021-03-02', '2021-03-19'),
                    new AllCardsMonriDiscount('2021-02-26', '2021-03-01', 0.15),
                    new AllCardsMonriDiscount('2021-03-02', '2024-03-01', 0.10),
                    
                ]);
                $discount = $discount_result['discount_percentage'];
                $mpc = self::getPriceForDiscount($product) * $product['quantity'];
                $mpc_with_discount = $mpc * (1 - $discount);
                $custom_params_products[] = [
                    'mpc' => $mpc,
                    'mpc_with_discount' => $mpc_with_discount,
                    'discount_percentage' => $discount,
                    'discount' => $discount,
                    'discount_amount' => $mpc - $mpc_with_discount,
                    'product_id' => $product['id_product'],
                    'discount_result' => $discount_result
                ];

                $total_price_with_discounts = $total_price_with_discounts + $mpc_with_discount;
            }

            $taxConfiguration = new TaxConfiguration();
            $total_price_with_discounts = $total_price_with_discounts + $cart->getPackageShippingCost();
            $discount_amount = $this->context->cart->getOrderTotal($taxConfiguration->includeTaxes()) - $total_price_with_discounts;
            if ($updatePrice) {
                if ($discount_amount > 0) {
                    Monri::addCartRule('monri_special_discount', 'Specijalni popust', $this->context, $discount_amount);
                    Monri::updatePayment($client_secret, intval(($total_price_with_discounts * 100)));
                } else {
                    Monri::disableCartRule('monri_special_discount', $this->context);
                }
                // update amount
            }

            $rv = [
                'total_price_with_discounts' => $total_price_with_discounts,
                'amount' => Tools::displayPrice($total_price_with_discounts),
                'discount_amount' => $discount_amount,
                'custom_params_products' => $custom_params_products
            ];
            die(Tools::jsonEncode($rv));
        } catch (Exception $exception) {
            die(Tools::jsonEncode(['error' => $exception,
                'trace' => $exception->getTraceAsString(),
//                'total_price_with_discounts' => $total_price_with_discounts
            ]));
        }
    }

    /** @noinspection PhpUnused */
    public function displayAjaxPrice()
    {
        $this->calculatePrice(false);
    }

    /**
     * @param $product
     * @param $discount_rules
     * @return array
     */
    static function getSpecialPriceDiscount($product, $discount_rules)
    {
        $product_id = $product['id_product'];
        // get all special prices
        $specific_prices = MonriWebServiceHelper::getSpecialPricesForProduct($product_id);

        $discount_percentage = 0;
        foreach ($discount_rules as $rule) {
            /**
             * @var $rule IMonriDiscount
             */

            if (!$rule->isEligible([], $product, $specific_prices)) {
                continue;
            }

            $discount_percentage = $rule->discountPercentage([], $product);

            if ($discount_percentage == 0) {
                continue;
            } else {
                return [
                    'discount_percentage' => $discount_percentage,
                    'name' => $rule->name()
                ];
            }
        }

        return [
            'discount_percentage' => $discount_percentage,
            'name' => 'unknown'
        ];
    }

}
