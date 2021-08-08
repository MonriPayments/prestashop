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

    function isEligible($request, $product, $specificPrices)
    {
        // TODO: Implement isEligible() method.
    }
}