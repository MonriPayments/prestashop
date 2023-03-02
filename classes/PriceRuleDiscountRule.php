<?php

class PriceRuleDiscountRule implements DiscountRule
{

    private $price_rule_name;

    /**
     * @param $price_rule_name
     */
    public function __construct($price_rule_name)
    {
        $this->price_rule_name = $price_rule_name;
    }

    function isEligible($request, $product, $specificPrices, $order_total)
    {
        foreach ($specificPrices as $specificPrice) {
            $specific_price_rule = MonriWebServiceHelper::getSpecificPriceRule($specificPrice['id_specific_price_rule'], $this->price_rule_name);
            if ($specific_price_rule == null) {
                continue;
            } else {
                return true;
            }
        }

        return false;
    }
}