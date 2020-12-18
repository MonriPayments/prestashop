<?php

interface IMonriDiscount {
    /**
     * Return 0 to continue to next discount
     * @param $request
     * @param $product
     * @return double
     */
    function discountPercentage($request, $product);

    /**
     * Returns true if it's applicable for product, request and special prices
     * @param $request
     * @param $product
     * @param $specificPrices
     * @return boolean
     */
    function isEligible($request, $product, $specificPrices);

    /**
     * A message used to show on client side
     * @return Stringable
     */
    function message();

    /**
     * @return string
     */
    function name();
}