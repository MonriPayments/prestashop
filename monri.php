<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


if (!defined('_PS_VERSION_')) {
    exit;
}

class MonriConstants
{
    const MODE_PROD = 'prod';
    const MODE_TEST = 'test';
    const KEY_MODE = 'MONRI_MODE';
    const KEY_MERCHANT_KEY_PROD = 'MONRI_MERCHANT_KEY_PROD';
    const KEY_MERCHANT_KEY_TEST = 'MONRI_MERCHANT_KEY_TEST';
    const KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD = 'MONRI_AUTHENTICITY_TOKEN_PROD';
    const KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST = 'MONRI_AUTHENTICITY_TOKEN_TEST';
    const KEY_MIN_INSTALLMENTS = 'KEY_MIN_INSTALLMENTS';
    const KEY_MAX_INSTALLMENTS = 'KEY_MAX_INSTALLMENTS';
}

class Monri extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    const MODE_PROD = 'prod';
    const MODE_TEST = 'test';
    const KEY_MODE = 'MONRI_MODE';
    const KEY_MERCHANT_KEY_PROD = 'MONRI_MERCHANT_KEY_PROD';
    const KEY_MERCHANT_KEY_TEST = 'MONRI_MERCHANT_KEY_TEST';
    const KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD = 'MONRI_AUTHENTICITY_TOKEN_PROD';
    const KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST = 'MONRI_AUTHENTICITY_TOKEN_TEST';

    public function __construct()
    {
        $this->name = 'monri';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->author = 'Monri';
        $this->controllers = ['validation', 'success', 'cancel', 'submit', 'callback'];
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
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn');
    }

    /**
     * Uninstall script
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall() && $this->removeConfigurationsFromDatabase();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption($params),
        ];

        return $payment_options;
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
        $externalOption = null;
        
        if(version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $externalOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        } else {
            if(!class_exists('Core_Business_Payment_PaymentOption')) {
                throw new \Exception(
                    sprintf('Class: Core_Business_Payment_PaymentOption not found or does not exist in PrestaShop v.%s', _PS_VERSION_)
                );
            }

            $externalOption = new Core_Business_Payment_PaymentOption();
        }

        if(!$externalOption) {
            throw new \Exception('Instance of PaymentOption not created. Check your PrestaShop version.');
        }

        $customer = $this->context->customer;
        $cart = $this->context->cart;
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $authenticity_token = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        $merchant_key = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
        $form_url = $mode == MonriConstants::MODE_PROD ? 'https://ipg.monri.com' : 'https://ipgtest.monri.com';
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

//        Correct test?
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

    public static function getMerchantKey()
    {
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        return Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
    }
}