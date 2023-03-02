<?php

class MonriDiscount implements IMonriDiscount
{
    private $monriDiscountPercentage;
    private $cardData;
    private $specific_price_rule;
    private $price_rule_name;
    private $default_discount;

    /**
     * MonriDiscount constructor.
     * @param $cardData
     * @param $price_rule_name
     * @param $default_discount
     */
    public function __construct($cardData, $price_rule_name, $default_discount)
    {
        $this->cardData = $cardData;
        $this->monriDiscountPercentage = 0;
        $this->price_rule_name = $price_rule_name;
        $this->default_discount = $default_discount;
    }

    function discountPercentage($request, $product)
    {
        if ($this->specific_price_rule == null) {
            return $this->default_discount;
        }

        return $this->monriDiscountPercentage;
    }

    function isEligible($request, $product, $specificPrices, $order_total)
    {
        $has_monri_discount = isset($this->cardData['discount']);

        if (!$has_monri_discount || !$this->cardData['discount']) {
            return false;
        }

        $monri_discount = $this->cardData['discount'];
        $original_amount = intval($monri_discount['original_amount']);
        $amount = intval($monri_discount['amount']);
        $this->monriDiscountPercentage = ($original_amount - $amount) / $original_amount;

        if ($this->monriDiscountPercentage == 0) {
            return false;
        }

        $this->specific_price_rule = null;

        foreach ($specificPrices as $specificPrice) {
            $this->specific_price_rule = MonriWebServiceHelper::getSpecificPriceRule($specificPrice['id_specific_price_rule'], $this->price_rule_name);
            if ($this->specific_price_rule == null) {
                continue;
            } else {
                return true;
            }
        }

        return false;
    }

    function message()
    {
        return $this->cardData['message'];
    }

    function name()
    {
        return "monri_pg_discount";
    }


}