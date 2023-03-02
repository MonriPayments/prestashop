<?php

class PriceMinMaxDiscountRule implements DiscountRule
{
    private $min;
    private $max;

    /**
     * @param $min
     * @param $max
     */
    public function __construct($min, $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function isEligible($request, $product, $specificPrices, $order_total)
    {
        return $order_total <= $this->max && $order_total >= $this->min;
    }
}