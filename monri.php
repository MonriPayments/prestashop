<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

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
        $this->controllers = array('validation');
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
            $this->getExternalPaymentOption(),
//            $this->getEmbeddedPaymentOption(),
//            $this->getIframePaymentOption(),
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

    public function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay external'))
//            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAction('https://ipgtest.monri.com/v2/form')
            ->setInputs(array (
                'utf8' =>
                    array (
                        'name' => 'utf8',
                        'type' => 'hidden',
                        'value' => '✓',
                    ),
                'authenticity_token' =>
                    array (
                        'name' => 'authenticity_token',
                        'type' => 'hidden',
                        'value' => '7db11ea5d4a1af32421b564c79b946d1ead3daf0',
                    ),
                'ch_full_name' =>
                    array (
                        'name' => 'ch_full_name',
                        'type' => 'hidden',
                        'value' => 'John Doe',
                    ),
                'ch_address' =>
                    array (
                        'name' => 'ch_address',
                        'type' => 'hidden',
                        'value' => 'Street 15',
                    ),
                'ch_city' =>
                    array (
                        'name' => 'ch_city',
                        'type' => 'hidden',
                        'value' => 'Old Town',
                    ),
                'ch_zip' =>
                    array (
                        'name' => 'ch_zip',
                        'type' => 'hidden',
                        'value' => '123bnm789',
                    ),
                'ch_country' =>
                    array (
                        'name' => 'ch_country',
                        'type' => 'hidden',
                        'value' => 'US',
                    ),
                'ch_phone' =>
                    array (
                        'name' => 'ch_phone',
                        'type' => 'hidden',
                        'value' => '00-123 456-7',
                    ),
                'ch_email' =>
                    array (
                        'name' => 'ch_email',
                        'type' => 'hidden',
                        'value' => 'email@email.com',
                    ),
                'order_info' =>
                    array (
                        'name' => 'order_info',
                        'type' => 'hidden',
                        'value' => 'snowmaster 3000',
                    ),
                'amount' =>
                    array (
                        'name' => 'amount',
                        'type' => 'hidden',
                        'value' => '100',
                    ),
                'order_number' =>
                    array (
                        'name' => 'order_number',
                        'type' => 'hidden',
                        'value' => '751bcc3deaa2ae5',
                    ),
                'currency' =>
                    array (
                        'name' => 'currency',
                        'type' => 'hidden',
                        'value' => 'NGN',
                    ),
                'transaction_type' =>
                    array (
                        'name' => 'transaction_type',
                        'type' => 'hidden',
                        'value' => 'purchase',
                    ),
                'number_of_installments' =>
                    array (
                        'name' => 'number_of_installments',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'cc_type_for_installments' =>
                    array (
                        'name' => 'cc_type_for_installments',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'installments_disabled' =>
                    array (
                        'name' => 'installments_disabled',
                        'type' => 'hidden',
                        'value' => 'false',
                    ),
                'force_cc_type' =>
                    array (
                        'name' => 'force_cc_type',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'moto' =>
                    array (
                        'name' => 'moto',
                        'type' => 'hidden',
                        'value' => 'false',
                    ),
                'digest' =>
                    array (
                        'name' => 'digest',
                        'type' => 'hidden',
                        'value' => '85b1627572a629b275789c717b81b5a8b3bc1a53ceda5cb3566d834818224dc9c636227968736803817a0962eac217e7a208ce27e887e7b4cf99884b8cc7940a',
                    ),
                'language' =>
                    array (
                        'name' => 'language',
                        'type' => 'hidden',
                        'value' => 'en',
                    ),
                'tokenize_pan_until' =>
                    array (
                        'name' => 'tokenize_pan_until',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'custom_params' =>
                    array (
                        'name' => 'custom_params',
                        'type' => 'hidden',
                        'value' => '{a:b, c:d}',
                    ),
                'tokenize_pan' =>
                    array (
                        'name' => 'tokenize_pan',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'tokenize_pan_offered' =>
                    array (
                        'name' => 'tokenize_pan_offered',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'tokenize_brands' =>
                    array (
                        'name' => 'tokenize_brands',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'whitelisted_pan_tokens' =>
                    array (
                        'name' => 'whitelisted_pan_tokens',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'custom_attributes' =>
                    array (
                        'name' => 'custom_attributes',
                        'type' => 'hidden',
                        'value' => '',
                    ),
                'form' =>
                    array (
                        'name' => 'form',
                        'type' => 'hidden',
                        'value' => 'Submit to WebPay form',
                    ),
            ))
            ->setAdditionalInformation($this->context->smarty->fetch('module:monri/views/templates/front/payment_infos.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.jpg'));

        return $externalOption;
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
        $value_key_merchant_key = Monri::keyForMonriMerchantKey($mode);
        $value_key_authenticity_token = Monri::keyForMonriAuthenticityToken($mode);
        $authenticity_token = Tools::getValue($value_key_merchant_key);
        $merchant_key = (string)Tools::getValue($value_key_authenticity_token);

        Configuration::updateValue($value_key_merchant_key, $merchant_key);
        Configuration::updateValue($value_key_authenticity_token, $authenticity_token);
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
}
