<?php

class DateValidFromToDiscountRule implements DiscountRule
{

    private $valid_from;
    private $valid_to;

    /**
     * @param $valid_from
     * @param $valid_to
     */
    public function __construct($valid_from, $valid_to)
    {
        $this->valid_from = $valid_from;
        $this->valid_to = $valid_to;
    }


    function isEligible($request, $product, $specificPrices, $order_total)
    {
        return MonriUtils::isDateBetween($this->valid_from, $this->valid_to);
    }
}