<?php

class MonriCardDiscount implements IMonriDiscount {
    private $discount_percentage;
    private $valid_from;
    private $valid_to;
    private $specific_price_rule;

    /**
     * MonriCardDiscount constructor.
     * @param $discount_percentage
     * @param $valid_from
     * @param $valid_to
     */
    public function __construct($discount_percentage, $valid_from, $valid_to)
    {
        $this->discount_percentage = $discount_percentage;
        $this->valid_from = $valid_from;
        $this->valid_to = $valid_to;
    }

    function discountPercentage($request, $product)
    {
        if ($this->specific_price_rule != null) {
            return $this->discount_percentage;
        }
        return 0;
    }

    function isEligible($request, $product, $specificPrices)
    {
        $now = date('Y-m-d');
        $active = $this->valid_from <= $now && $now <= $this->valid_to;

        if (!$active) {
            return false;
        }

        $this->specific_price_rule = null;

        foreach ($specificPrices as $specificPrice) {
            $this->specific_price_rule = MonriWebServiceHelper::getSpecificPriceRule($specificPrice['id_specific_price_rule'], 'Debit');
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
        return "Plaćajte debitnim karticama i ostvarite 15% popusta na kupovinu odabranog asortimana u IMTEC web shopu-u od 18.12.2020 do 24.12.2020 ili do isteka zaliha.";
    }
}