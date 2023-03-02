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
        try {
            $tz = 'Europe/Sarajevo';
            $timezone = new DateTimeZone($tz);
            $from = strtotime((new DateTime($from, $timezone))->format('Y-m-d H:i:s'));
            $to = strtotime((new DateTime($to, $timezone))->format('Y-m-d H:i:s'));
            $now = strtotime((new DateTime('now', $timezone))->format('Y-m-d H:i:s'));
            return $from <= $now && $now <= $to;
        } catch (Exception $e) {
            // TODO: handle this
            return false;
        }
    }

    static function isTodayWeekDay($day) {
        $currentDate = new DateTime("now", new DateTimeZone("Europe/Sarajevo"));
        return $currentDate->format('N') == $day;
    }
}