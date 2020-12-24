<?php

class AllCardsMonriDiscount implements IMonriDiscount
{
    private $from;
    private $to;
    private $discount;


    /**
     * AllCardsMonriDiscount constructor.
     * @param $from
     * @param $to
     * @param $discount
     */
    public function __construct($from, $to, $discount)
    {
        $this->from = $from;
        $this->to = $to;
        $this->discount = $discount;
    }

    function discountPercentage($request, $product)
    {
        return $this->discount;
    }

    function isEligible($request, $product, $specificPrices)
    {
        return MonriUtils::isDateBetween($this->from, $this->to);
    }

    function message()
    {
        return "";
    }

    function name()
    {
        return "default";
    }


}