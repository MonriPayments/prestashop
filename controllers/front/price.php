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
    const DEFAULT_DISCOUNT = 0.10;

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
            $card_data = isset($_POST['card_data']) ? $_POST['card_data'] : [];
            $taxConfiguration = new TaxConfiguration();
            $order_total = $this->context->cart->getOrderTotal($taxConfiguration->includeTaxes());
            foreach ($products as $product) {
                $discount_result = self::getSpecialPriceDiscount($product, $card_data, [
                    new CompositeDiscount(0.20, "
                     Svakog petka u godini štedite 20% prilikom kupovine koju obavite sa BBI MASTERCARD I VISA KARTICAMA. Cijena se odnosi na plaćanje putem webshopa i u IMTEC poslovnicama!
                     ", [
                        new DateValidFromToDiscountRule('2023-02-20', '2024-02-20'),
                        new BinDiscountRule(["540057", "517267", "529784", "406970", "428172", "428173", "406971"]),
                        new WeekDayDiscountRule(5)
                    ]),
                    new AllCardsMonriDiscount('2021-10-01', '2024-03-01', self::DEFAULT_DISCOUNT)
                ], $order_total);

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


            $total_price_with_discounts = $total_price_with_discounts + $cart->getPackageShippingCost();
            $discount_amount = $this->context->cart->getOrderTotal($taxConfiguration->includeTaxes()) - $total_price_with_discounts;
            $updated_payment = ['status' => 'Not updated'];
            if ($updatePrice) {
                if ($discount_amount > 0) {
                    Monri::addCartRule('monri_special_discount', 'Specijalni popust', $this->context, $discount_amount);
                } else {
                    Monri::disableCartRule('monri_special_discount', $this->context);
                }
                // update amount
                $updated_payment = Monri::updatePayment($client_secret, intval(($total_price_with_discounts * 100)));
            }

            $rv = [
                'total_price_with_discounts' => $total_price_with_discounts,
                'amount' => Tools::displayPrice($total_price_with_discounts),
                'discount_amount' => $discount_amount,
                'custom_params_products' => $custom_params_products,
                'updated_payment' => $updated_payment,
                'update_price' => $updatePrice,
            ];

            die(Tools::jsonEncode($rv));
        } catch (Throwable $exception) {
            die(Tools::jsonEncode(['error' => $exception,
                'trace' => $exception->getTraceAsString(),
//                'total_price_with_discounts' => $total_price_with_discounts
            ]));
        }
    }

    /** @noinspection PhpUnused */
    public function displayAjaxPrice()
    {
        try {
            $this->calculatePrice(false);
        } catch (Exception $exception) {
            var_dump($exception);
            die("An error has occurred");
        }
    }

    /**
     * @param $product
     * @param $discount_rules
     * @return array
     */
    static function getSpecialPriceDiscount($product, $card_data, $discount_rules, $order_total)
    {
        $product_id = $product['id_product'];
        // get all special prices
        $specific_prices = MonriWebServiceHelper::getSpecialPricesForProduct($product_id);

        $discount_percentage = 0;
        foreach ($discount_rules as $rule) {
            /**
             * @var $rule IMonriDiscount
             */

            if (!$rule->isEligible(['card_data' => $card_data], $product, $specific_prices, $order_total)) {
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
