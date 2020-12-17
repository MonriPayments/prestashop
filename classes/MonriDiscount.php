<?php

class MonriDiscount implements IMonriDiscount
{
    private $monriDiscountPercentage;
    private $cardData;
    private $specific_price_rule;

    /**
     * MonriDiscount constructor.
     * @param $cardData
     */
    public function __construct($cardData)
    {
        $this->cardData = $cardData;
    }

    function discountPercentage($request, $product)
    {
        if ($this->specific_price_rule == null) {
            return 0;
        }

        return $this->monriDiscountPercentage;
    }

    function isEligible($request, $product, $specificPrices)
    {
        $has_monri_discount = isset($this->cardData['discount']);

        if (!$has_monri_discount) {
            return false;
        }

        $this->monriDiscountPercentage = 0;

        $monri_discount = $this->cardData['discount'];
        $original_amount = intval($monri_discount['original_amount']);
        $amount = intval($monri_discount['amount']);
        $this->monriDiscountPercentage = ($original_amount - $amount) / $original_amount;

        if ($this->monriDiscountPercentage == 0) {
            return false;
        }

        $this->specific_price_rule = null;

        foreach ($specificPrices as $specificPrice) {
            $this->specific_price_rule = MonriWebServiceHelper::getSpecificPriceRule($specificPrice['id_specific_price_rule'], 'UCB');
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
}