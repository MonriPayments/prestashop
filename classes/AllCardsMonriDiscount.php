<?php

class AllCardsMonriDiscount implements IMonriDiscount {

    function discountPercentage($request, $product)
    {
        return 0.10;
    }

    function isEligible($request, $product, $specificPrices)
    {
        return true;
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