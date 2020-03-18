<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
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
        $this->controllers = ['validation', 'success', 'cancel'];
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

        $externalOption = new PaymentOption();
        $customer = $this->context->customer;
        $cart = $this->context->cart;
        $mode = Configuration::get(self::KEY_MODE);
        $authenticity_token = Configuration::get($mode == self::MODE_PROD ? self::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : self::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        $merchant_key = Configuration::get($mode == self::MODE_PROD ? self::KEY_MERCHANT_KEY_PROD : self::KEY_MERCHANT_KEY_TEST);
        $form_url = $mode == self::MODE_PROD ? 'https://ipg.monri.com' : 'https://ipgtest.monri.com';

        $address = new Address($cart->id_address_delivery);

        $currency = new Currency($cart->id_currency);
        $amount = "" . ((int)((double)$cart->getOrderTotal() * 100));
        $order_number = "cart_" . $cart->id;

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
            'amount' =>
                [
                    'name' => 'amount',
                    'type' => 'hidden',
                    'value' => $amount
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
                    // TODO: discuss this, how it's set?
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
                    'value' => 'false',
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
            'digest' =>
                [
                    'name' => 'digest',
                    'type' => 'hidden',
                    'value' => $this->calculateFormV2Digest($merchant_key, $order_number, $amount, $currency->iso_code),
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
                ],
            'form' =>
                [
                    'name' => 'form',
                    'type' => 'hidden',
                    'value' => 'Submit to WebPay form',
                ],
        ];

//        echo '<pre>' . var_export($inputs, true) . '</pre>';
//        die();

//        Correct test?
        $externalOption->setCallToActionText($this->l('Pay using Monri'))
            ->setAction("$form_url/v2/form")
            ->setInputs($inputs);
        // TODO: additional information on method type?
//            ->setAdditionalInformation($this->context->smarty->fetch('module:monri/views/templates/front/payment_infos.tpl'))
//            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.jpg'));

        return $externalOption;
    }

    private function calculateFormV2Digest($merchant_key, $order_number, $amount, $currency)
    {
        return hash('sha512', $merchant_key . $order_number . $amount . $currency);
    }

    public function getEmbeddedPaymentOption()
    {
        $embeddedOption = new PaymentOption();
        $embeddedOption->setCallToActionText($this->l('Pay embedded'))
            ->setForm($this->generateForm())
            ->setAdditionalInformation($this->context->smarty->fetch('module:monri/views/templates/front/payment_infos.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.jpg'));

        return $embeddedOption;
    }

    public function getIframePaymentOption()
    {
        $iframeOption = new PaymentOption();
        $iframeOption->setCallToActionText($this->l('Pay iframe'))
            ->setAction($this->context->link->getModuleLink($this->name, 'iframe', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:monri/views/templates/front/payment_infos.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.jpg'));

        return $iframeOption;
    }

    protected function generateForm()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = sprintf("%02d", $i);
        }

        $years = [];
        for ($i = 0; $i <= 10; $i++) {
            $years[] = date('Y', strtotime('+' . $i . ' years'));
        }

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'months' => $months,
            'years' => $years,
        ]);

        return $this->context->smarty->fetch('module:monri/views/templates/front/payment_form.tpl');
    }

    private static function keyForMonriMerchantKey($mode)
    {
        return "MONRI_MERCHANT_KEY_" . strtoupper($mode);
    }

    private static function keyForMonriAuthenticityToken($mode)
    {
        return "MONRI_AUTHENTICITY_TOKEN_" . strtoupper($mode);
    }

    private function updateConfiguration($mode)
    {
        $update_keys = [
            $mode == self::MODE_PROD ? self::KEY_MERCHANT_KEY_PROD : self::KEY_MERCHANT_KEY_TEST,
            $mode == self::MODE_PROD ? self::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : self::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST
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
            $output .= $this->displayError($this->l('Invalid Configuration value for Merchant Key Live'));
        }

        // validating the input
        if (empty($authenticity_token) || !Validate::isGenericName($authenticity_token)) {
            $output .= $this->displayError($this->l('Invalid Configuration value for Api Key Live'));
        }

        return $output;
    }

    /**
     * shows the configuration page in the back-end
     */
    public function getContent()
    {
        $output = null;

        if (!Tools::isSubmit('submit' . $this->name)) {
            return $output . $this->displayForm();
        } else {
            // get post values
            $mode = (string)Tools::getValue('MONRI_MODE');

            if ($mode != self::MODE_PROD && $mode != self::MODE_TEST) {
                $output .= $this->displayError($this->l("Invalid Mode, expected: live or test got '$mode'"));
                return $output . $this->displayForm();
            } else {
                $test_validate = $this->validateConfiguration(self::MODE_TEST);
                $live_validate = $mode == self::MODE_PROD ? $this->validateConfiguration(self::MODE_PROD) : null;
                if ($test_validate == null && $live_validate == null) {
                    $this->updateConfiguration(self::MODE_PROD);
                    $this->updateConfiguration(self::MODE_TEST);
                    Configuration::updateValue('MONRI_MODE', $mode);
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
                    'name' => 'MONRI_MERCHANT_KEY_TEST',
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('TODO')
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Merchant key for Live'),
                    'name' => 'MONRI_MERCHANT_KEY_LIVE',
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('TODO')
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Test/Production Mode'),
                    'name' => self::KEY_MODE,
                    'class' => 't',
                    'values' => [
                        [
                            'id' => self::MODE_PROD,
                            'value' => self::MODE_PROD,
                            'label' => $this->l('Production')
                        ],
                        [
                            'id' => self::MODE_TEST,
                            'value' => self::MODE_TEST,
                            'label' => $this->l('Test')
                        ]
                    ],
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Authenticity token for Test'),
                    'name' => 'MONRI_AUTHENTICITY_TOKEN_TEST',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Authenticity-Token please contact support@monri.com')
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Authenticity token for Live'),
                    'name' => 'MONRI_AUTHENTICITY_TOKEN_LIVE',
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
            $merchant_key_live = (string)Tools::getValue(self::KEY_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = (string)Tools::getValue(self::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD);

            $mode = (string)Tools::getValue(self::KEY_MODE);

            $merchant_key_test = (string)Tools::getValue(self::KEY_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = (string)Tools::getValue(self::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        } else {
            $merchant_key_live = Configuration::get(self::KEY_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = Configuration::get(self::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD);

            $mode = Configuration::get('MONRI_MODE');

            $merchant_key_test = Configuration::get(self::KEY_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = Configuration::get(self::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        }

        // Load current value
        $helper->fields_value[self::KEY_MERCHANT_KEY_PROD] = $merchant_key_live;
        $helper->fields_value[self::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD] = $merchant_authenticity_token_live;

        $helper->fields_value[self::KEY_MERCHANT_KEY_TEST] = $merchant_key_test;
        $helper->fields_value[self::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST] = $merchant_authenticity_token_test;
        $helper->fields_value[self::KEY_MODE] = $mode;

        return $helper->generateForm($fields_form);
    }

    /**
     * Removes Adyen settings from configuration table
     *
     * @return bool
     */
    private function removeConfigurationsFromDatabase()
    {
        $names = [
            self::KEY_MODE,
            self::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST,
            self::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD,
            self::KEY_MERCHANT_KEY_TEST,
            self::KEY_MERCHANT_KEY_PROD
        ];

        $db = Db::getInstance();
        /** @noinspection SqlWithoutWhere SqlResolve */
        return $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` IN ("' . implode('", "', $names) . '") ');
    }

    public static function getMerchantKey()
    {
        $mode = Configuration::get(self::KEY_MODE);
        return Configuration::get($mode == self::MODE_PROD ? self::KEY_MERCHANT_KEY_PROD : self::KEY_MERCHANT_KEY_TEST);
    }
}
