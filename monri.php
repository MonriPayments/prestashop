<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

include_once 'classes/MonriConstants.php';
include_once 'classes/MonriUtils.php';
include_once 'classes/MonriPaymentFee.php';
include_once 'classes/MonriWebServiceHelper.php';


class Monri extends PaymentModule
{
    const INSTALL_SQL_FILE = 'install.sql';
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'monri';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->author = 'Monri';
        $this->controllers = ['validation', 'success', 'cancel', 'submit'];
        $this->is_eu_compatible = 1;

        $this->currencies = true;

        $this->bootstrap = true;
        parent::__construct();

        $this->meta_title = $this->l('Monri');
        $this->displayName = $this->l('Monri');
        $this->description = $this->l('Accept all payments offered by Monri');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Verifies if the current PrestaShop version is supported or not by the plugin
     *
     * @return bool
     */
    public function isPrestaShopSupportedVersion()
    {
        return version_compare(_PS_VERSION_, '1.6', '>');
    }

    public function install()
    {
        if (!$this->isPrestaShopSupportedVersion()) {
            $this->_errors[] = $this->l('Sorry, this module is not compatible with your version.');
            return false;
        }

        return parent::install()
            && $this->installDb()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionFrontControllerSetMedia');
    }

    private function installDb()
    {
        if (!file_exists(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
            return (false);
        } elseif (!$sql = Tools::file_get_contents(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
            return (false);
        }

        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), $sql);
        $sql = preg_split("/;\s*[\r\n]+/", $sql);

        foreach ($sql as $query) {
            if ($query) {
                if (!Db::getInstance()->execute(trim($query))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Uninstall script
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall()
            && $this->removeConfigurationsFromDatabase()
            && $this->uninstallDb();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        return [
//            $this->getExternalPaymentOption($params),
            $this->getEmbeddedPaymentOption($params)
        ];
    }

    static function updatePayment($client_secret, $amount)
    {
        $authenticity_token = Monri::getAuthenticityToken();
        $key = Monri::getMerchantKey();
        $base_url = Monri::baseUrl();
        $body_as_string = json_encode(['amount' => intval($amount)]);
        $timestamp = time();
        $digest = hash('sha512', $key . $timestamp . $authenticity_token . $body_as_string);
        $authorization = "WP3-v2 $authenticity_token $timestamp $digest";
        $ch = curl_init($base_url . "/v2/payment/$client_secret/update");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body_as_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body_as_string),
                'Authorization: ' . $authorization
            )
        );

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return [
                'error' => curl_error($ch),
                'status' => 'error'
            ];
        } else {
            curl_close($ch);
            return [
                'response' => json_decode($result, true),
                'status' => 'approved'
            ];
        }
    }

    private function createPayment($data, $key, $authenticity_token, $base_url)
    {
        $body_as_string = json_encode($data); // use php's standard library equivalent if Json::encode is not available in your code
        $ch = curl_init($base_url . '/v2/payment/new');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body_as_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);


        $timestamp = time();
        $digest = hash('sha512', $key . $timestamp . $authenticity_token . $body_as_string);
        $authorization = "WP3-v2 $authenticity_token $timestamp $digest";

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body_as_string),
                'Authorization: ' . $authorization
            )
        );

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return ['client_secret' => null, 'status' => 'declined', 'error' => curl_error($ch)];
        } else {
            curl_close($ch);
            return ['status' => 'approved', 'client_secret' => json_decode($result, true)['client_secret']];
        }
    }

    public function getEmbeddedPaymentOption($params)
    {
        try {

            $customer = $this->context->customer;
            $cart = $this->context->cart;
            self::disableCartRule('ucbm_discount', $this->context);
            $base_url = Monri::baseUrl();
            $authenticity_token = Monri::getAuthenticityToken();
            $merchant_key = Monri::getMerchantKey();

            $address = new Address($cart->id_address_delivery);

            $address_delivery = new Address($cart->id_address_delivery);
            $address_delivery_country = new Country($address_delivery->id_country);
            $iso_code = $address_delivery_country->iso_code;

            $currency = new Currency($cart->id_currency);
            $amount = ((int)((double)$cart->getOrderTotal() * 100));
            $order_number = $cart->id . "_" . time();

            $data = [
                'amount' => $amount, //minor units = 1EUR
                // unique order identifier
                'order_number' => $order_number,
                'currency' => $currency->iso_code,
                'transaction_type' => 'purchase',
                'order_info' => "Order {$cart->id}",
                'scenario' => 'charge',
                'ch_full_name' => "{$customer->firstname} {$customer->lastname}",
                'ch_address' => MonriUtils::valueOrDefault($address->address1, "N/A"),
                'ch_city' => MonriUtils::valueOrDefault($address->city, "N/A"),
                'ch_zip' => MonriUtils::valueOrDefault($address->postcode, "N/A"),
                'ch_country' => $iso_code,
                'ch_phone' => MonriUtils::valueOrDefault($address->phone, "N/A"),
                'ch_email' => $customer->email,
                // TODO: bs
                'language' => 'hr',
                'custom_attributes' => [
                    'discounts' => [
                        'client_manages_discounts' => true
                    ]
                ]
            ];
            $paymentResponse = $this->createPayment($data, $merchant_key, $authenticity_token, $base_url);

            if ($paymentResponse['client_secret'] != null) {
                $embeddedOption = new PaymentOption();
                $form_url = $this->context->link->getModuleLink($this->name, 'check', array(), true);
                $embeddedOption
                    ->setCallToActionText($this->l('Monri - Plaćanje karticom'))
                    ->setAction($form_url)
                    ->setForm($this->generateWorkingForm([
                        'client_secret' => $paymentResponse['client_secret'],
                        'base_url' => $base_url,
                        'authenticity_token' => $authenticity_token
                    ]));

                return $embeddedOption;
            } else {
                return null;
            }
        } catch (Exception $exception) {
            var_dump('Error ' . $exception);
            die();
            return null;
        }
    }

    public function hookActionValidateOrder($params)
    {
        if (!$this->active) {
            return false;
        }

        $order = $params['order'];
        if ($order->payment != "Monri") {
            Monri::disableCartRule('ucbm_discount', $this->context);
            return false;
        }

        return true;
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        // List of front controllers where we set the assets
        $frontControllers = array('order', 'order-confirmation', 'order-opc');
        $controller = $this->context->controller;
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $base_url = $mode == MonriConstants::MODE_PROD ? 'https://ipg.monri.com' : 'https://ipgtest.monri.com';

        if (in_array($controller->php_self, $frontControllers)) {

            Media::addJsDef(array(
                'static_token' => Tools::getToken(false),
            ));

            $controller->registerJavascript(
                'monri-components',
                $base_url . "/dist/components.js",
                [
                    'priority' => 200,
                ]
            );

            $controller->registerJavascript(
                'monri-confirm-order',
                'modules/monri/views/js/order.js',
                [
                    'priority' => 200,
                ]
            );
        }
    }

    protected function generateWorkingForm($params)
    {
        $this->context->smarty->assign($params);
        return $this->context->smarty->fetch('module:monri/views/templates/front/payment_form.tpl');
    }

    /**
     *
     */
    public function hookPaymentReturn()
    {
        if (!$this->active) {
            return null;
        }
        return;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getExternalPaymentOption($params)
    {

        $externalOption = new PaymentOption();
        $customer = $this->context->customer;
        $cart = $this->context->cart;
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $authenticity_token = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        $form_url = $this->context->link->getModuleLink($this->name, 'submit', array(), true);

        $address = new Address($cart->id_address_delivery);

        $currency = new Currency($cart->id_currency);
        $amount = "" . ((int)((double)$cart->getOrderTotal() * 100));
        $order_number = $cart->id . "_" . time();

        $inputs = [
            'utf8' =>
                [
                    'name' => 'utf8',
                    'type' => 'hidden',
                    'value' => '✓',
                ],
            'authenticity_token' =>
                [
                    'name' => 'authenticity_token',
                    'type' => 'hidden',
                    'value' => $authenticity_token
                ],
            'ch_full_name' =>
                [
                    'name' => 'ch_full_name',
                    'type' => 'hidden',
                    'value' => "{$customer->firstname} {$customer->lastname}"
                ],
            'ch_address' =>
                [
                    'name' => 'ch_address',
                    'type' => 'hidden',
                    'value' => $address->address1
                ],
            'ch_city' =>
                [
                    'name' => 'ch_city',
                    'type' => 'hidden',
                    'value' => $address->city
                ],
            'ch_zip' =>
                [
                    'name' => 'ch_zip',
                    'type' => 'hidden',
                    'value' => $address->postcode
                ],
            'ch_country' =>
                [
                    'name' => 'ch_country',
                    'type' => 'hidden',
                    'value' => $address->country
                ],
            'ch_phone' =>
                [
                    'name' => 'ch_phone',
                    'type' => 'hidden',
                    'value' => $address->phone
                ],
            'ch_email' =>
                [
                    'name' => 'ch_email',
                    'type' => 'hidden',
                    'value' => $customer->email
                ],
            'order_info' =>
                [
                    'name' => 'order_info',
                    'type' => 'hidden',
                    'value' => "Order {$cart->id}"
                ],
            'order_number' =>
                [
                    'name' => 'order_number',
                    'type' => 'hidden',
                    // TODO: discuss this
                    'value' => $order_number
                ],
            'currency' =>
                [
                    'name' => 'currency',
                    'type' => 'hidden',
                    'value' => $currency->iso_code,
                ],
            'transaction_type' =>
                [
                    'name' => 'transaction_type',
                    'type' => 'hidden',
                    'value' => 'purchase',
                ],
            'number_of_installments' =>
                [
                    'name' => 'number_of_installments',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'cc_type_for_installments' =>
                [
                    'name' => 'cc_type_for_installments',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'installments_disabled' =>
                [
                    'name' => 'installments_disabled',
                    'type' => 'hidden',
                    'value' => 'true',
                ],
            'force_cc_type' =>
                [
                    'name' => 'force_cc_type',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'moto' =>
                [
                    'name' => 'moto',
                    'type' => 'hidden',
                    'value' => 'false',
                ],
            'language' =>
                [
                    'name' => 'language',
                    'type' => 'hidden',
                    'value' => 'en',
                ],
            'tokenize_pan_until' =>
                [
                    'name' => 'tokenize_pan_until',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'custom_params' =>
                [
                    'name' => 'custom_params',
                    'type' => 'hidden',
                    'value' => '{}',
                ],
            'tokenize_pan' =>
                [
                    'name' => 'tokenize_pan',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'tokenize_pan_offered' =>
                [
                    'name' => 'tokenize_pan_offered',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'tokenize_brands' =>
                [
                    'name' => 'tokenize_brands',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'whitelisted_pan_tokens' =>
                [
                    'name' => 'whitelisted_pan_tokens',
                    'type' => 'hidden',
                    'value' => '',
                ],
            'custom_attributes' =>
                [
                    'name' => 'custom_attributes',
                    'type' => 'hidden',
                    'value' => '',
                ]
        ];

        $new_inputs = [];
        foreach ($inputs as $k => $v) {
            $new_inputs["monri_$k"] = [
                'name' => "monri_" . $k,
                'type' => 'hidden',
                'value' => $v['value'],
            ];
        }

        $new_inputs['monri_module_name'] = [
            'name' => 'monri_module_name',
            'type' => 'hidden',
            'value' => 'monri',
        ];

        $externalOption->setCallToActionText($this->l('Pay using Monri - Kartično plaćanje'))
            ->setAction($form_url)
            ->setInputs($new_inputs);

        return $externalOption;
    }

    private function updateConfiguration($mode)
    {
        $update_keys = [
            $mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST,
            $mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST
        ];

        foreach ($update_keys as $key) {
            Configuration::updateValue($key, (string)Tools::getValue($key));
        }
    }

    private function validateConfiguration($mode)
    {
        $mode_uppercase = strtoupper($mode);
        $authenticity_token = Tools::getValue("MONRI_AUTHENTICITY_TOKEN_$mode_uppercase");
        $merchant_key = (string)Tools::getValue("MONRI_MERCHANT_KEY_$mode_uppercase");

        $output = null;

        // validating the input
        if (empty($merchant_key) || !Validate::isGenericName($merchant_key)) {
            $output .= $this->displayError($this->l("Invalid Configuration value for Merchant Key $mode"));
        }

        // validating the input
        if (empty($authenticity_token) || !Validate::isGenericName($authenticity_token)) {
            $output .= $this->displayError($this->l("Invalid Configuration value for Api Key $mode"));
        }

        return $output;
    }

    /**
     * shows the configuration page in the back-end
     * @noinspection PhpUnused
     */
    public function getContent()
    {
        $output = null;

        if (!Tools::isSubmit('submit' . $this->name)) {
            return $output . $this->displayForm();
        } else {
            // get post values
            $mode = (string)Tools::getValue(MonriConstants::KEY_MODE);

            if ($mode != MonriConstants::MODE_PROD && $mode != MonriConstants::MODE_TEST) {
                $output .= $this->displayError($this->l("Invalid Mode, expected: live or test got '$mode'"));
                return $output . $this->displayForm();
            } else {
                $test_validate = $this->validateConfiguration(MonriConstants::MODE_TEST);
                $live_validate = $mode == MonriConstants::MODE_PROD ? $this->validateConfiguration(MonriConstants::MODE_PROD) : null;
                if ($test_validate == null && $live_validate == null) {
                    $this->updateConfiguration(MonriConstants::MODE_PROD);
                    $this->updateConfiguration(MonriConstants::MODE_TEST);
                    Configuration::updateValue(MonriConstants::KEY_MODE, $mode);
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                } else {
                    $output .= $test_validate . $live_validate;
                }
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * @return mixed
     */
    public function displayForm()
    {
        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('General Settings'),
                'image' => '../img/admin/edit.gif'
            ),
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Merchant key for Test'),
                    'name' => MonriConstants::KEY_MERCHANT_KEY_TEST,
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('TODO')
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Merchant key for Production'),
                    'name' => MonriConstants::KEY_MERCHANT_KEY_PROD,
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('TODO')
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Test/Production Mode'),
                    'name' => MonriConstants::KEY_MODE,
                    'class' => 't',
                    'values' => [
                        [
                            'id' => MonriConstants::MODE_PROD,
                            'value' => MonriConstants::MODE_PROD,
                            'label' => $this->l('Production')
                        ],
                        [
                            'id' => MonriConstants::MODE_TEST,
                            'value' => MonriConstants::MODE_TEST,
                            'label' => $this->l('Test')
                        ]
                    ],
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Authenticity token for Test'),
                    'name' => MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST,
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Authenticity-Token please contact support@monri.com')
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Authenticity token for Prod'),
                    'name' => MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD,
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Authenticity-Token please contact support@monri.com')
                ]
            ],
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        if (Tools::isSubmit('submit' . $this->name)) {
            // get settings from post because post can give errors and you want to keep values
            $merchant_key_live = (string)Tools::getValue(MonriConstants::KEY_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = (string)Tools::getValue(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD);

            $mode = (string)Tools::getValue(MonriConstants::KEY_MODE);

            $merchant_key_test = (string)Tools::getValue(MonriConstants::KEY_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = (string)Tools::getValue(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        } else {
            $merchant_key_live = Configuration::get(MonriConstants::KEY_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = Configuration::get(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD);

            $mode = Configuration::get(MonriConstants::KEY_MODE);

            $merchant_key_test = Configuration::get(MonriConstants::KEY_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = Configuration::get(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        }

        // Load current value
        $helper->fields_value[MonriConstants::KEY_MERCHANT_KEY_PROD] = $merchant_key_live;
        $helper->fields_value[MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD] = $merchant_authenticity_token_live;

        $helper->fields_value[MonriConstants::KEY_MERCHANT_KEY_TEST] = $merchant_key_test;
        $helper->fields_value[MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST] = $merchant_authenticity_token_test;
        $helper->fields_value[MonriConstants::KEY_MODE] = $mode;

        return $helper->generateForm($fields_form);
    }

    /**
     * Removes Monri settings from configuration table
     *
     * @return bool
     */
    private function removeConfigurationsFromDatabase()
    {
        return true;
        $names = [
            MonriConstants::KEY_MODE,
            MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST,
            MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD,
            MonriConstants::KEY_MERCHANT_KEY_TEST,
            MonriConstants::KEY_MERCHANT_KEY_PROD
        ];

        $db = Db::getInstance();
        /** @noinspection SqlWithoutWhere SqlResolve */
        return $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` IN ("' . implode('", "', $names) . '") ');
    }

    public function uninstallDb()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'monri_paymentfee`'
        );
    }

    public static function getMerchantKey()
    {
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        return Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
    }

    public static function getAuthenticityToken()
    {
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        return Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
    }

    public static function baseUrl()
    {
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        return $mode == MonriConstants::MODE_PROD ? 'https://ipg.monri.com' : 'https://ipgtest.monri.com';
    }

    public static function baseShopUrl()
    {
        return Context::getContext()->shop->getBaseURL(true);
    }

    public static function getPrestashopWebServiceApiKey()
    {
        // TODO: add a a monri plugin
        return 'ikaRpebIzB96FOJu944FN0H2CRafjj33';
    }

    public static function getPrestashopAuthenticationHeader()
    {
        return base64_encode(Monri::getPrestashopWebServiceApiKey() . ':');
    }

    public static function webServiceGetJson($path)
    {
        $authorizationKey = Monri::getPrestashopAuthenticationHeader();
        $url = Monri::webServiceUrl($path);
        return Monri::curlGetJSON($url, array("Authorization: Basic $authorizationKey"));
    }

    public static function webServiceUrl($path)
    {
        return Monri::baseShopUrl() . $path;
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

    public static function xmlToArray($input)
    {
        $xml = simplexml_load_string($input);
        $json = json_encode($xml);
        return json_decode($json, TRUE);
    }

    public static function applyMonriDiscount()
    {

    }

    public static function disableCartRule($cart_rule_name, $context)
    {
        $isExistCartRules = MonriPaymentFee::getIdCartRuleByIdCart(
            $context->cart->id,
            $context->customer->id,
            $cart_rule_name
        );

        if (count($isExistCartRules) > 0) {
            foreach ($isExistCartRules as $isExistCartRule) {
                $objCartRule = new CartRule($isExistCartRule['id_cart_rule']);
                $context->cart->removeCartRule($isExistCartRule['id_cart_rule']);
                $context->cart->save();

                if (Validate::isLoadedObject($objCartRule)) {
                    $objCartRule->delete();
                }
            }

        }
    }

    public static function addCartRule($cart_rule_name, $discount_name, $context, $feeAmount)
    {
        self::disableCartRule($cart_rule_name, $context);
        // 1. check if we already created rule
        // 2. if not create new one
        // 1. save in db
        // 2. check if we have id cart rule
        // 3.

        $monriPaymentFee = new MonriPaymentFee();
        $objCartRule = new CartRule();
        $objCartRule->code = Tools::passwdGen();
        $objCartRule->name = array();
        $objCartRule->quantity_per_user = 1;
        foreach (Language::getLanguages(true) as $lang) {
            $objCartRule->name[$lang['id_lang']] = $discount_name;
        }
        $objCartRule->id_customer = $context->customer->id;
        $objCartRule->reduction_amount = $feeAmount;
        $objCartRule->date_from = date('Y-m-d H:00:00');
        $objCartRule->date_to = date('Y-m-d H:00:00', strtotime('+ 60 minute'));
        $objCartRule->quantity = 1;
        $objCartRule->partial_use = 0;
        $objCartRule->reduction_tax = 1;
        $objCartRule->reduction_currency = $context->currency->id;
        $objCartRule->active = 1;
        $objCartRule->save();
        $context->cart->addCartRule($objCartRule->id);
        $monriPaymentFee->id_cart_rule = $objCartRule->id;
        $monriPaymentFee->id_customer = $context->customer->id;
        $monriPaymentFee->id_cart = $context->cart->id;
        $monriPaymentFee->is_used = 0;
        $monriPaymentFee->name = $cart_rule_name;
        $monriPaymentFee->save();

        return $objCartRule;
    }
}