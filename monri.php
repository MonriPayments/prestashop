<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


if (!defined('_PS_VERSION_')) {
    exit;
}

class MonriConstants
{
    const MODE_PROD = 'prod';
    const MODE_TEST = 'test';

	const PAYMENT_GATEWAY_SERVICE_TYPE = 'PAYMENT_GATEWAY_SERVICE_TYPE';

	const PAYMENT_TYPE_MONRI_WEBPAY = 'monri_webpay';

	const PAYMENT_TYPE_MONRI_WSPAY = 'monri_wspay';

	const MONRI_WSPAY_VERSION = '2.0';

    const KEY_MODE = 'MONRI_MODE';
    const MONRI_MERCHANT_KEY_PROD = 'MONRI_MERCHANT_KEY_PROD';
    const MONRI_MERCHANT_KEY_TEST = 'MONRI_MERCHANT_KEY_TEST';
    const MONRI_AUTHENTICITY_TOKEN_PROD = 'MONRI_AUTHENTICITY_TOKEN_PROD';
    const MONRI_AUTHENTICITY_TOKEN_TEST = 'MONRI_AUTHENTICITY_TOKEN_TEST';

	const MONRI_WSPAY_SHOP_ID_PROD = 'MONRI_WSPAY_SHOP_ID_PROD';
	const MONRI_WSPAY_SHOP_ID_TEST = 'MONRI_WSPAY_SHOP_ID_TEST';
	const MONRI_WSPAY_FORM_SECRET_PROD = 'MONRI_WSPAY_FORM_SECRET_PROD';
	const MONRI_WSPAY_FORM_SECRET_TEST = 'MONRI_WSPAY_FORM_SECRET_TEST';
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

    public function __construct()
    {
        $this->name = 'monri';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->author = 'Monri';
        $this->controllers = ['validation', 'success', 'cancel', 'webPaySubmit', 'webPaySuccess', 'WSPaySubmit', 'WSPaySuccess', 'error'];
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
	    $payment_service_type = Configuration::get(MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE);

		$payment_options = [];
		switch ($payment_service_type) {
			case MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY:
				$payment_options[] = $this->getMonriWebPayExternalPaymentOption($params);
				break;
			case MonriConstants::PAYMENT_TYPE_MONRI_WSPAY:
				$payment_options[] = $this->getMonriWSPayExternalPaymentOption($params);
				break;
		}

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

    public function getMonriWebPayExternalPaymentOption($params)
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
        $authenticity_token = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_AUTHENTICITY_TOKEN_PROD : MonriConstants::MONRI_AUTHENTICITY_TOKEN_TEST);
        $merchant_key = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_MERCHANT_KEY_PROD : MonriConstants::MONRI_MERCHANT_KEY_TEST);
        $form_url = $this->context->link->getModuleLink($this->name, 'webPaySubmit', array(), true);
	    $success_url = $this->context->link->getModuleLink($this->name, 'webPaySuccess', array(), true);
	    $cancel_url = $this->context->link->getModuleLink($this->name, 'cancel', array(), true);

        $address = new Address($cart->id_address_delivery);

        $currency = new Currency($cart->id_currency);
        $amount = "" . ((int)((double)$cart->getOrderTotal() * 100));
        $order_number = $cart->id . "_" . time();
		$_SESSION['order_number'] = $order_number;

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
                ],
            'success_url_override' =>
	            [
		            'name' => 'success_url_override',
		            'type' => 'hidden',
		            'value' => $success_url,
	            ],
            'cancel_url_override' =>
	            [
		            'name' => 'cancel_url_override',
		            'type' => 'hidden',
		            'value' => $cancel_url,
	            ],
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

	public function getMonriWSPayExternalPaymentOption()
	{

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
		$language = strtoupper($this->context->language->iso_code);
		$mode = Configuration::get(MonriConstants::KEY_MODE);
		$shop_id = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_WSPAY_SHOP_ID_PROD : MonriConstants::MONRI_WSPAY_SHOP_ID_TEST);
		$form_url = $this->context->link->getModuleLink($this->name, 'WSPaySubmit', array(), true);
		$success_url = $this->context->link->getModuleLink($this->name, 'WSPaySuccess', array(), true);
		$cancel_url = $this->context->link->getModuleLink($this->name, 'cancel', array(), true);
		$error_url = $this->context->link->getModuleLink($this->name, 'error', array(), true);

		$address = new Address($cart->id_address_delivery);
		$amount = number_format( $cart->getOrderTotal(), 2, ',', '' );
		$cart_id = $cart->id . "_" . time();
		//will be used to validate on success page
		$_SESSION['cart_id'] = $cart_id;

		$inputs = [
			'Version' =>
				[
					'name' => 'Version',
					'type' => 'hidden',
					'value' => MonriConstants::MONRI_WSPAY_VERSION
				],
			'ShopID' =>
				[
					'name' => 'ShopID',
					'type' => 'hidden',
					'value' => $shop_id
				],
			'ShoppingCartID' =>
				[
					'name' => 'ShoppingCartID',
					'type' => 'hidden',
					// TODO: discuss this
					'value' => $cart_id
				],
			'Lang' =>
				[
					'name' => 'Lang',
					'type' => 'hidden',
					'value' => $language
				],
			'TotalAmount' =>
				[
					'name' => 'TotalAmount',
					'type' => 'hidden',
					'value' => $amount
				],
			'ReturnUrl' =>
				[
					'name' => 'ReturnUrl',
					'type' => 'hidden',
					'value' => $success_url
				],
			'CancelUrl' =>
				[
					'name' => 'CancelUrl',
					'type' => 'hidden',
					'value' => $cancel_url
				],
			'ReturnErrorURL' =>
				[
					'name' => 'ReturnErrorURL',
					'type' => 'hidden',
					'value' => $error_url
				],
			'CustomerFirstName' =>
				[
					'name' => 'CustomerFirstName',
					'type' => 'hidden',
					'value' => $customer->firstname
				],
			'CustomerLastName' =>
				[
					'name' => 'CustomerLastName',
					'type' => 'hidden',
					'value' => $customer->lastname
				],
			'CustomerAddress' =>
				[
					'name' => 'CustomerAddress',
					'type' => 'hidden',
					'value' => $address->address1
				],
			'CustomerCity' =>
				[
					'name' => 'CustomerCity',
					'type' => 'hidden',
					'value' => $address->city
				],
			'CustomerZIP' =>
				[
					'name' => 'CustomerZIP',
					'type' => 'hidden',
					'value' => $address->postcode
				],
			'CustomerCountry' =>
				[
					'name' => 'CustomerCountry',
					'type' => 'hidden',
					'value' => $address->country
				],
			'CustomerPhone' =>
				[
					'name' => 'CustomerPhone',
					'type' => 'hidden',
					'value' => $address->phone
				],
			'CustomerEmail' =>
				[
					'name' => 'CustomerEmail',
					'type' => 'hidden',
					'value' => $customer->email
				],
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
		$externalOption->setCallToActionText($this->l('Pay using Monri WSPay - Kartično plaćanje'))
		               ->setAction($form_url)
		               ->setInputs($new_inputs);

		return $externalOption;
	}

    private function updateConfiguration($mode)
    {
        $update_keys = [
            $mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_MERCHANT_KEY_PROD : MonriConstants::MONRI_MERCHANT_KEY_TEST,
            $mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_AUTHENTICITY_TOKEN_PROD : MonriConstants::MONRI_AUTHENTICITY_TOKEN_TEST,
	        $mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD : MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST,
	        $mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_WSPAY_SHOP_ID_PROD : MonriConstants::MONRI_WSPAY_SHOP_ID_TEST,

        ];

        foreach ($update_keys as $key) {
            Configuration::updateValue($key, (string)Tools::getValue($key));
        }
    }

    private function validateConfiguration($mode)
    {
        $mode_uppercase = strtoupper($mode);
        $monri_webpay_authenticity_token = Tools::getValue("MONRI_AUTHENTICITY_TOKEN_$mode_uppercase");
        $monri_webpay_merchant_key = (string)Tools::getValue("MONRI_MERCHANT_KEY_$mode_uppercase");
	    $monri_wspay_form_secret = (string)Tools::getValue("MONRI_WSPAY_FORM_SECRET_$mode_uppercase");
	    $monri_wspay_shop_id = (string)Tools::getValue("MONRI_WSPAY_SHOP_ID_$mode_uppercase");

        $output = null;

        // validating the input
        if (empty($monri_webpay_merchant_key) || !Validate::isGenericName($monri_webpay_merchant_key)) {
            $output .= $this->displayError($this->l("Invalid Configuration value for Monri WebPay Merchant Key $mode"));
        }

        // validating the input
        if (empty($monri_webpay_authenticity_token) || !Validate::isGenericName($monri_webpay_authenticity_token)) {
            $output .= $this->displayError($this->l("Invalid Configuration value for Monri WebPay Api Key $mode"));
        }

	    // validating the input
	    if (empty($monri_wspay_form_secret) || !Validate::isGenericName($monri_wspay_form_secret)) {
		    $output .= $this->displayError($this->l("Invalid Configuration value for Monri WSPay secret key $mode"));
	    }

	    // validating the input
	    if (empty($monri_wspay_shop_id) || !Validate::isGenericName($monri_wspay_shop_id)) {
		    $output .= $this->displayError($this->l("Invalid Configuration value for Monri WSPay shop id $mode"));
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
			$payment_type = (string)Tools::getValue(MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE);

            if ($mode != MonriConstants::MODE_PROD && $mode != MonriConstants::MODE_TEST) {
                $output .= $this->displayError($this->l("Invalid Mode, expected: prod or test got '$mode'"));
                return $output . $this->displayForm();
            } else {
                $test_validate = $this->validateConfiguration(MonriConstants::MODE_TEST);
                $live_validate = $this->validateConfiguration(MonriConstants::MODE_PROD);
                if ($test_validate == null && $live_validate == null) {
                    $this->updateConfiguration(MonriConstants::MODE_PROD);
                    $this->updateConfiguration(MonriConstants::MODE_TEST);
                    Configuration::updateValue(MonriConstants::KEY_MODE, $mode);
	                Configuration::updateValue(MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE, $payment_type);
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
                    'label' => $this->l('Monri WebPay merchant key for Test'),
                    'name' => MonriConstants::MONRI_MERCHANT_KEY_TEST,
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('TODO')
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Monri WebPay merchant key for Production'),
                    'name' => MonriConstants::MONRI_MERCHANT_KEY_PROD,
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
                    'label' => $this->l('Monri WebPay Authenticity token for Test'),
                    'name' => MonriConstants::MONRI_AUTHENTICITY_TOKEN_TEST,
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Authenticity-Token please contact support@monri.com')
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Monri WebPay Authenticity token for Prod'),
                    'name' => MonriConstants::MONRI_AUTHENTICITY_TOKEN_PROD,
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Authenticity-Token please contact support@monri.com')
                ],
	            [
		            'type' => 'radio',
		            'label' => $this->l('Payment gateway service'),
		            'name' => MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE,
		            'class' => 't',
		            'values' => [
			            [
				            'id' => MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY,
				            'value' => MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY,
				            'label' => $this->l('Monri WebPay')
			            ],
			            [
				            'id' => MonriConstants::PAYMENT_TYPE_MONRI_WSPAY,
				            'value' => MonriConstants::PAYMENT_TYPE_MONRI_WSPAY,
				            'label' => $this->l('Monri WSPay')
			            ]
		            ],
		            'required' => true
	            ],
	            [
		            'type' => 'text',
		            'label' => $this->l('Secret key for Monri WSPay Test'),
		            'name' => MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST,
		            'size' => 20,
		            'required' => false,
		            'hint' => $this->l('If you don\'t know your secret key please contact wspay@wspay.info')
	            ],
	            [
		            'type' => 'text',
		            'label' => $this->l('Secret key for Monri WSPay Prod'),
		            'name' => MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD,
		            'size' => 20,
		            'required' => false,
		            'hint' => $this->l('If you don\'t know your secret key please contact wspay@wspay.info')
	            ],
	            [
		            'type' => 'text',
		            'label' => $this->l('Monri WSPay shop id for Test'),
		            'name' => MonriConstants::MONRI_WSPAY_SHOP_ID_TEST,
		            'size' => 20,
		            'required' => true,
		            'lang' => false,
		            'hint' => $this->l('TODO')
	            ],
	            [
		            'type' => 'text',
		            'label' => $this->l('Monri WSPay shop id for Prod'),
		            'name' => MonriConstants::MONRI_WSPAY_SHOP_ID_PROD,
		            'size' => 20,
		            'required' => true,
		            'lang' => false,
		            'hint' => $this->l('TODO')
	            ],
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
            $merchant_key_live = (string)Tools::getValue(MonriConstants::MONRI_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = (string)Tools::getValue(MonriConstants::MONRI_AUTHENTICITY_TOKEN_PROD);

            $mode = (string)Tools::getValue(MonriConstants::KEY_MODE);

            $merchant_key_test = (string)Tools::getValue(MonriConstants::MONRI_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = (string)Tools::getValue(MonriConstants::MONRI_AUTHENTICITY_TOKEN_TEST);
	        $monri_wspay_form_secret_test = (string)Tools::getValue(MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST);
	        $monri_wspay_form_secret_prod = (string)Tools::getValue(MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD);
	        $payment_gateway_service_type = (string)Tools::getValue(MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE);
	        $monri_wspay_shop_id_test = (string)Tools::getValue(MonriConstants::MONRI_WSPAY_SHOP_ID_TEST);
	        $monri_wspay_shop_id_prod = (string)Tools::getValue(MonriConstants::MONRI_WSPAY_SHOP_ID_PROD);

        } else {
            $merchant_key_live = Configuration::get(MonriConstants::MONRI_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = Configuration::get(MonriConstants::MONRI_AUTHENTICITY_TOKEN_PROD);

            $mode = Configuration::get(MonriConstants::KEY_MODE);

            $merchant_key_test = Configuration::get(MonriConstants::MONRI_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = Configuration::get(MonriConstants::MONRI_AUTHENTICITY_TOKEN_TEST);
	        $monri_wspay_form_secret_test = Configuration::get(MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST);
	        $monri_wspay_form_secret_prod = Configuration::get(MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD);
	        $payment_gateway_service_type = Configuration::get(MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE);
	        $monri_wspay_shop_id_test = Configuration::get(MonriConstants::MONRI_WSPAY_SHOP_ID_TEST);
	        $monri_wspay_shop_id_prod = Configuration::get(MonriConstants::MONRI_WSPAY_SHOP_ID_PROD);
        }

        // Load current value
        $helper->fields_value[MonriConstants::MONRI_MERCHANT_KEY_PROD] = $merchant_key_live;
        $helper->fields_value[MonriConstants::MONRI_AUTHENTICITY_TOKEN_PROD] = $merchant_authenticity_token_live;

        $helper->fields_value[MonriConstants::MONRI_MERCHANT_KEY_TEST] = $merchant_key_test;
        $helper->fields_value[MonriConstants::MONRI_AUTHENTICITY_TOKEN_TEST] = $merchant_authenticity_token_test;
        $helper->fields_value[MonriConstants::KEY_MODE] = $mode;
	    $helper->fields_value[MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST] = $monri_wspay_form_secret_test;
	    $helper->fields_value[MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD] = $monri_wspay_form_secret_prod;
	    $helper->fields_value[MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE] = $payment_gateway_service_type;
	    $helper->fields_value[MonriConstants::MONRI_WSPAY_SHOP_ID_TEST] = $monri_wspay_shop_id_test;
	    $helper->fields_value[MonriConstants::MONRI_WSPAY_SHOP_ID_PROD] = $monri_wspay_shop_id_prod;

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
            MonriConstants::MONRI_AUTHENTICITY_TOKEN_TEST,
            MonriConstants::MONRI_AUTHENTICITY_TOKEN_PROD,
            MonriConstants::MONRI_MERCHANT_KEY_TEST,
            MonriConstants::MONRI_MERCHANT_KEY_PROD,
	        MonriConstants::MONRI_WSPAY_SHOP_ID_PROD,
	        MonriConstants::MONRI_WSPAY_SHOP_ID_TEST,
	        MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST,
	        MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD,
	        MonriConstants::PAYMENT_GATEWAY_SERVICE_TYPE
        ];

        $db = Db::getInstance();
        /** @noinspection SqlWithoutWhere SqlResolve */
        return $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` IN ("' . implode('", "', $names) . '") ');
    }

    public static function getMonriWebPayMerchantKey()
    {
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        return Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_MERCHANT_KEY_PROD : MonriConstants::MONRI_MERCHANT_KEY_TEST);
    }

	public static function getMonriWSPaySecretKey()
	{
		$mode = Configuration::get(MonriConstants::KEY_MODE);
		return Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::MONRI_WSPAY_FORM_SECRET_PROD : MonriConstants::MONRI_WSPAY_FORM_SECRET_TEST);
	}
}