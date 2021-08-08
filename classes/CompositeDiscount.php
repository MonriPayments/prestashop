<?php

class CompositeDiscount implements IMonriDiscount
{
    private $discountPercentage;
    private $rules;
    private $message;

    /**
     * @param $discountPercentage
     * @param $message
     * @param $rules
     */
    public function __construct($discountPercentage, $message, $rules)
    {
        $this->discountPercentage = $discountPercentage;
        $this->rules = $rules;
        $this->message = $message;
    }


    function discountPercentage($request, $product)
    {
        return $this->discountPercentage;
    }

    function isEligible($request, $product, $specificPrices)
    {
        if (count($this->rules) == 0) {
            return false;
        }

        foreach ($this->rules as $rule) {
            if ($rule instanceof DiscountRule) {
                $eligible = $rule->isEligible($request, $product, $specificPrices);
                if (!$eligible) {
                    return false;
                }
            }
        }

        return true;
    }

    function message()
    {
        return $this->message;
    }

    function name()
    {
        return "monri_composite_discount";
    }


}