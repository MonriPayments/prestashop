<?php

class WeekDayDiscountRule implements DiscountRule
{

    private $week_day;

    /**
     * @param $week_day
     */
    public function __construct($week_day)
    {
        $this->week_day = $week_day;
    }

    function isEligible($request, $product, $specificPrices, $order_total)
    {
        return MonriUtils::isTodayWeekDay($this->week_day);
    }
}