<?php

class MonriUtils {
    static function valueOrDefault($v, $default) {
        if ($v) {
            return $v;
        } else {
            return $default;
        }
    }

    /**
     * @param $from
     * @param $to
     * @return bool
     */
    static function isDateBetween($from, $to) {
        $from = strtotime($from);
        $to = strtotime($to);
        $now = strtotime(date('Y-m-d'));
        return $from <= $now && $now <= $to;
    }
}