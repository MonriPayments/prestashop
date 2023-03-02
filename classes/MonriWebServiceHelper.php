<?php

class MonriWebServiceHelper
{
    public static function getPrestashopWebServiceApiKey()
    {
        return Configuration::get(MonriConstants::KEY_WEB_SERVICE_KEY);
    }

    public static function getPrestashopAuthenticationHeader()
    {
        return base64_encode(self::getPrestashopWebServiceApiKey() . ':');
    }

    /**
     * @param $product_id
     * @return array
     */
    public static function getSpecialPricesForProduct($product_id)
    {
        $rv = self::webServiceGetJson("/api/specific_prices/?filter[id_product]=$product_id&output_format=JSON");
        if ($rv['http_code'] !== 200) {
            return [];
        }
        $special_prices = [];
        foreach ($rv['response']['specific_prices'] as $price) {
            $v = self::getSpecificPrice($price['id']);
            if ($v != null) {
                $special_prices[] = $v;
            }
        }
        return $special_prices;
    }

    public static function getSpecificPriceRuleRaw($id)
    {
        if (!$id) {
            return null;
        }

        $rv = self::webServiceGetJson("/api/specific_price_rules/$id?output_format=JSON");
        if (isset($rv['response']['specific_price_rule'])) {
            $specific_price_rule = $rv['response']['specific_price_rule'];
            return $specific_price_rule;
        } else {
            return null;
        }
    }

    public static function getSpecificPriceRule($id, $name)
    {
        if (!$id) {
            return null;
        }

        $rv = self::webServiceGetJson("/api/specific_price_rules/$id?output_format=JSON");
        if (isset($rv['response']['specific_price_rule'])) {
            $specific_price_rule = $rv['response']['specific_price_rule'];
            if (strpos($specific_price_rule['name'], $name) === 0) {
                return $specific_price_rule;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public static function getSpecificPrice($id)
    {
        if (!$id) {
            return null;
        } else {
            $rv = self::webServiceGetJson("/api/specific_prices/$id?output_format=JSON");
            if ($rv['http_code'] !== 200) {
                return null;
            }
            return $rv['response']['specific_price'];
        }
    }

    public static function webServiceGetJson($path)
    {
        $authorizationKey = self::getPrestashopAuthenticationHeader();
        $url = self::webServiceUrl($path);
        return self::curlGetJSON($url, array("Authorization: Basic $authorizationKey"));
    }

    public static function webServiceUrl($path)
    {
        return self::baseShopUrl() . $path;
    }

    public static function baseShopUrl()
    {
        return Context::getContext()->shop->getBaseURL(true);
    }

    public static function curlGetJSON($url, $headers)
    {
        $request = curl_init();
        $headers[] = 'Accept: application/json';
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 0
        ];
        // Set options
        curl_setopt_array($request, $curlOptions);
        $apiResponse = curl_exec($request);
        $httpCode = (int)curl_getinfo($request, CURLINFO_HTTP_CODE);
        $curlDetails = curl_getinfo($request);
        curl_close($request);
        return [
            'response' => json_decode($apiResponse, true),
            'http_code' => $httpCode,
            'curl_details' => $curlDetails,
            'request_headers' => $headers,
            'url' => $url
        ];
    }

    public static function curlPostXml($url, $xml)
    {
        $request = curl_init();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array('Content-Type: application/xml', 'Accept: application/xml'),
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 0
        ];
        // Set options
        curl_setopt_array($request, $curlOptions);
        $apiResponse = curl_exec($request);
        $httpCode = (int)curl_getinfo($request, CURLINFO_HTTP_CODE);
        $curlDetails = curl_getinfo($request);
        curl_close($request);
        return [
            'response' => $apiResponse,
            'http_code' => $httpCode,
            'curl_details' => $curlDetails
        ];
    }
}