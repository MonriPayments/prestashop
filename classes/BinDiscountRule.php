<?php

class BinDiscountRule implements DiscountRule
{
    /**
     * @var array
     */
    private $bins;

    /**
     * @param array $bins
     */
    public function __construct(array $bins)
    {
        $this->bins = $bins;
    }

    function isEligible($request, $product, $specificPrices)
    {
        $has_bin = isset($request['card_data']['bin']);

        if (!$has_bin) {
            return false;
        }

        return in_array($request['card_data']['bin'], $this->bins);
    }
}