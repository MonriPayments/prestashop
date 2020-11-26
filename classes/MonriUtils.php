<?php

class MonriUtils {
    static function valueOrDefault($v, $default) {
        if ($v) {
            return $v;
        } else {
            return $default;
        }
    }
}