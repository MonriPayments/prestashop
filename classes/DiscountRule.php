<?php

interface DiscountRule
{
    function isEligible($request, $product, $specificPrices, $order_total);
}