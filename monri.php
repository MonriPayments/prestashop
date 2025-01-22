<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MonriConstants
{
    const MODE_PROD = 'prod';
    const MODE_TEST = 'test';

    const TRANSACTION_TYPE_AUTHORIZE = 'authorize';
    const TRANSACTION_TYPE_CAPTURE = 'capture';
    const MONRI_TRANSACTION_TYPE = 'MONRI_TRANSACTION_TYPE';
    const MONRI_PAYMENT_GATEWAY_SERVICE_TYPE = 'MONRI_PAYMENT_GATEWAY_SERVICE_TYPE';

    const PAYMENT_TYPE_MONRI_WEBPAY = 'monri_webpay';

    const PAYMENT_TYPE_MONRI_WSPAY = 'monri_wspay';

	const PAYMENT_TYPE_MONRI_COMPONENTS = 'monri_components';

    const MONRI_WSPAY_VERSION = '2.0';

    const KEY_MODE = 'MONRI_MODE';
    const KEY_MERCHANT_KEY_PROD = 'MONRI_MERCHANT_KEY_PROD';
    const KEY_MERCHANT_KEY_TEST = 'MONRI_MERCHANT_KEY_TEST';
    const KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD = 'MONRI_AUTHENTICITY_TOKEN_PROD';
    const KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST = 'MONRI_AUTHENTICITY_TOKEN_TEST';

    const KEY_MIN_INSTALLMENTS = 'KEY_MIN_INSTALLMENTS';
    const KEY_MAX_INSTALLMENTS = 'KEY_MAX_INSTALLMENTS';

	const MONRI_WEBPAY_PRODUCTION_URL = 'https://ipg.monri.com';
	const MONRI_WEBPAY_TEST_URL = 'https://ipgtest.monri.com';
	const MONRI_WSPAY_PRODUCTION_URL = 'https://form.wspay.biz/authorization.aspx';
	consT MONRI_WSPAY_TEST_URL = 'https://formtest.wspay.biz/authorization.aspx';

	const MONRI_COMPONENTS_AUTHORIZATION_ENDPOINT_TEST = 'https://ipgtest.monri.com/v2/payment/new';
	const MONRI_COMPONENTS_AUTHORIZATION_ENDPOINT = 'https://ipg.monri.com/v2/payment/new';

	const MONRI_COMPONENTS_SCRIPT_ENDPOINT_TEST = 'https://ipgtest.monri.com/dist/components.js';
	const MONRI_COMPONENTS_SCRIPT_ENDPOINT = 'https://ipg.monri.com/dist/components.js';
}

class Monri extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = [];

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'monri';
        $this->tab = 'payments_gateways';
        $this->version = '1.2.0';
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
        $payment_service_type = Configuration::get(MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE);

        $payment_options = [];
        switch ($payment_service_type) {
            case MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY:
                $payment_options[] = $this->getMonriWebPayExternalPaymentOption($params);
                break;
            case MonriConstants::PAYMENT_TYPE_MONRI_WSPAY:
                $payment_options[] = $this->getMonriWSPayExternalPaymentOption();
                break;
	        case MonriConstants::PAYMENT_TYPE_MONRI_COMPONENTS:
		        $payment_options[] = $this->getMonriComponentsExternalPaymentOption();
		        break;

        }

        return $payment_options;
    }

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

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $externalOption = new PaymentOption();
        } else {
            if (!class_exists('Core_Business_Payment_PaymentOption')) {
                throw new Exception(sprintf('Class: Core_Business_Payment_PaymentOption not found or does not exist in PrestaShop v.%s', _PS_VERSION_));
            }

            $externalOption = new Core_Business_Payment_PaymentOption();
        }

        if (!$externalOption) {
            throw new Exception('Instance of PaymentOption not created. Check your PrestaShop version.');
        }

        $customer = $this->context->customer;
        $cart = $this->context->cart;
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $authenticity_token = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
        $merchant_key = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
        $form_url = $this->context->link->getModuleLink($this->name, 'webPaySubmit', [], true);
        $success_url = $this->context->link->getModuleLink($this->name, 'webPaySuccess', [], true);
        $cancel_url = $this->context->link->getModuleLink($this->name, 'cancel', [], true);
        $transaction_type = Configuration::get(MonriConstants::MONRI_TRANSACTION_TYPE) === MonriConstants::TRANSACTION_TYPE_CAPTURE ?
        'purchase' : 'authorize';

        $address = new Address($cart->id_address_delivery);

        $currency = new Currency($cart->id_currency);
        $order_number = $cart->id . '_' . time();

        $inputs = [
            'utf8' => [
                'name' => 'utf8',
                'type' => 'hidden',
                'value' => '✓',
            ],
            'authenticity_token' => [
                'name' => 'authenticity_token',
                'type' => 'hidden',
                'value' => $authenticity_token,
            ],
            'ch_full_name' => [
                'name' => 'ch_full_name',
                'type' => 'hidden',
                'value' => "{$customer->firstname} {$customer->lastname}",
            ],
            'ch_address' => [
                'name' => 'ch_address',
                'type' => 'hidden',
                'value' => $address->address1,
            ],
            'ch_city' => [
                'name' => 'ch_city',
                'type' => 'hidden',
                'value' => $address->city,
            ],
            'ch_zip' => [
                'name' => 'ch_zip',
                'type' => 'hidden',
                'value' => $address->postcode,
            ],
            'ch_country' => [
                'name' => 'ch_country',
                'type' => 'hidden',
                'value' => $address->country,
            ],
            'ch_phone' => [
                'name' => 'ch_phone',
                'type' => 'hidden',
                'value' => $address->phone,
            ],
            'ch_email' => [
                'name' => 'ch_email',
                'type' => 'hidden',
                'value' => $customer->email,
            ],
            'order_info' => [
                'name' => 'order_info',
                'type' => 'hidden',
                'value' => "Order {$cart->id}",
            ],
            'order_number' => [
                'name' => 'order_number',
                'type' => 'hidden',
                // TODO: discuss this
                'value' => $order_number,
            ],
            'currency' => [
                'name' => 'currency',
                'type' => 'hidden',
                'value' => $currency->iso_code,
            ],
            'transaction_type' => [
                'name' => 'transaction_type',
                'type' => 'hidden',
                'value' => $transaction_type,
            ],
            'number_of_installments' => [
                'name' => 'number_of_installments',
                'type' => 'hidden',
                'value' => '',
            ],
            'cc_type_for_installments' => [
                'name' => 'cc_type_for_installments',
                'type' => 'hidden',
                'value' => '',
            ],
            'installments_disabled' => [
                'name' => 'installments_disabled',
                'type' => 'hidden',
                'value' => 'true',
            ],
            'force_cc_type' => [
                'name' => 'force_cc_type',
                'type' => 'hidden',
                'value' => '',
            ],
            'moto' => [
                'name' => 'moto',
                'type' => 'hidden',
                'value' => 'false',
            ],
            'language' => [
                'name' => 'language',
                'type' => 'hidden',
                'value' => 'en',
            ],
            'tokenize_pan_until' => [
                'name' => 'tokenize_pan_until',
                'type' => 'hidden',
                'value' => '',
            ],
            'custom_params' => [
                'name' => 'custom_params',
                'type' => 'hidden',
                'value' => '{}',
            ],
            'tokenize_pan' => [
                'name' => 'tokenize_pan',
                'type' => 'hidden',
                'value' => '',
            ],
            'tokenize_pan_offered' => [
                'name' => 'tokenize_pan_offered',
                'type' => 'hidden',
                'value' => '',
            ],
            'tokenize_brands' => [
                'name' => 'tokenize_brands',
                'type' => 'hidden',
                'value' => '',
            ],
            'whitelisted_pan_tokens' => [
                'name' => 'whitelisted_pan_tokens',
                'type' => 'hidden',
                'value' => '',
            ],
            'custom_attributes' => [
                'name' => 'custom_attributes',
                'type' => 'hidden',
                'value' => '',
            ],
            'success_url_override' => [
                'name' => 'success_url_override',
                'type' => 'hidden',
                'value' => $success_url,
            ],
            'cancel_url_override' => [
                'name' => 'cancel_url_override',
                'type' => 'hidden',
                'value' => $cancel_url,
            ],
        ];

        $new_inputs = [];
        foreach ($inputs as $k => $v) {
            $new_inputs["monri_$k"] = [
                'name' => 'monri_' . $k,
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

	public function getMonriComponentsExternalPaymentOption()
	{

		if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
			$externalOption = new PaymentOption();
		} else {
			if (!class_exists('Core_Business_Payment_PaymentOption')) {
				throw new Exception(sprintf('Class: Core_Business_Payment_PaymentOption not found or does not exist in PrestaShop v.%s', _PS_VERSION_));
			}

			$externalOption = new Core_Business_Payment_PaymentOption();
		}

		if (!$externalOption) {
			throw new Exception('Instance of PaymentOption not created. Check your PrestaShop version.');
		}

		$mode = Configuration::get(MonriConstants::KEY_MODE);
		$url = $mode == MonriConstants::MODE_PROD ?
			MonriConstants::MONRI_COMPONENTS_AUTHORIZATION_ENDPOINT : MonriConstants::MONRI_COMPONENTS_AUTHORIZATION_ENDPOINT_TEST;
		$script_url = $mode == MonriConstants::MODE_PROD ?
			MonriConstants::MONRI_COMPONENTS_SCRIPT_ENDPOINT : MonriConstants::MONRI_COMPONENTS_SCRIPT_ENDPOINT_TEST;
		$cart = $this->context->cart;
		$amount_in_minor_units = (int) round( $cart->getCartTotalPrice() * 100 );
		$currency_order = new Currency($cart->id_currency);
		$transaction_type = Configuration::get(MonriConstants::MONRI_TRANSACTION_TYPE) === MonriConstants::TRANSACTION_TYPE_CAPTURE ?
			'purchase' : 'authorize';
		$authenticity_token = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
		$merchant_key = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
		//todo: save client secret in session so that if customer refreshes page we do not have to make another request
		$order_number = $cart->id . '_' . time();

		Context::getContext()->cookie->__set('order_number', $order_number);

		$data = [
			'amount'           => $amount_in_minor_units,
			'order_number'     => $order_number,
			'currency'         => $currency_order->iso_code,
			'transaction_type' => $transaction_type,
			'order_info'       => 'prestashop order'
		];

		$data = json_encode($data);
		$timestamp = time();
		$digest    = hash( 'sha512',
			$merchant_key .
			$timestamp .
			$authenticity_token .
			$data
		);

		$authorization = "WP3-v2 {$authenticity_token} $timestamp $digest";

		PrestaShopLogger::addLog('Monri Components  data: ' . $data);

		$options = [
			'http' => [
				'method'  => 'POST',
				'header'  => [
					'Content-Type: application/json',
					'Authorization: ' . $authorization,
				],
				'content' => $data,
				'timeout' => 10
			]
		];

		$response = Tools::file_get_contents($url, false, stream_context_create($options));
		PrestaShopLogger::addLog('Monri Components response: ' . $response);
		$response = json_decode($response, true);
		if (!isset($response['status']) || $response['status'] === 'error') {
			PrestaShopLogger::addLog('Something went wrong. Please check your credentials and try again.', 3 );
			throw new Exception($this->l('Something went wrong. Please check your credentials and try again.'));
		}
		$client_secret = $response['client_secret'];

		$this->context->smarty->assign([
			'clientSecret' => $client_secret,
			'scriptUrl' => $script_url,
			'authenticityToken' => $authenticity_token,
			'customerAddressId' => $cart->id_address_delivery
		]);

		$externalOption
			->setModuleName($this->name)
			->setCallToActionText($this->l('Pay using Monri Components - Kartično plaćanje'))
			->setForm($this->generateEmbeddedForm());


		return $externalOption;
	}

    public function getMonriWSPayExternalPaymentOption()
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $externalOption = new PaymentOption();
        } else {
            if (!class_exists('Core_Business_Payment_PaymentOption')) {
                throw new Exception(sprintf('Class: Core_Business_Payment_PaymentOption not found or does not exist in PrestaShop v.%s', _PS_VERSION_));
            }

            $externalOption = new Core_Business_Payment_PaymentOption();
        }

        if (!$externalOption) {
            throw new Exception('Instance of PaymentOption not created. Check your PrestaShop version.');
        }

        $customer = $this->context->customer;
        $cart = $this->context->cart;
        $language = strtoupper($this->context->language->iso_code);
        $mode = Configuration::get(MonriConstants::KEY_MODE);
        $shop_id = Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
        $form_url = $this->context->link->getModuleLink($this->name, 'WSPaySubmit', [], true);
        $success_url = $this->context->link->getModuleLink($this->name, 'WSPaySuccess', [], true);
        $cancel_url = $this->context->link->getModuleLink($this->name, 'cancel', [], true);
        $error_url = $this->context->link->getModuleLink($this->name, 'error', [], true);

        $address = new Address($cart->id_address_delivery);
        $cart_id = ($mode === MonriConstants::MODE_PROD ? $cart->id : $cart->id . '_' . time());

        $inputs = [
            'Version' => [
                'name' => 'Version',
                'type' => 'hidden',
                'value' => MonriConstants::MONRI_WSPAY_VERSION,
            ],
            'ShopID' => [
                'name' => 'ShopID',
                'type' => 'hidden',
                'value' => $shop_id,
            ],
            'ShoppingCartID' => [
                'name' => 'ShoppingCartID',
                'type' => 'hidden',
                // TODO: discuss this
                'value' => $cart_id,
            ],
            'Lang' => [
                'name' => 'Lang',
                'type' => 'hidden',
                'value' => $language,
            ],
            'ReturnUrl' => [
                'name' => 'ReturnUrl',
                'type' => 'hidden',
                'value' => $success_url,
            ],
            'CancelUrl' => [
                'name' => 'CancelUrl',
                'type' => 'hidden',
                'value' => $cancel_url,
            ],
            'ReturnErrorURL' => [
                'name' => 'ReturnErrorURL',
                'type' => 'hidden',
                'value' => $error_url,
            ],
            'CustomerFirstName' => [
                'name' => 'CustomerFirstName',
                'type' => 'hidden',
                'value' => $customer->firstname,
            ],
            'CustomerLastName' => [
                'name' => 'CustomerLastName',
                'type' => 'hidden',
                'value' => $customer->lastname,
            ],
            'CustomerAddress' => [
                'name' => 'CustomerAddress',
                'type' => 'hidden',
                'value' => $address->address1,
            ],
            'CustomerCity' => [
                'name' => 'CustomerCity',
                'type' => 'hidden',
                'value' => $address->city,
            ],
            'CustomerZIP' => [
                'name' => 'CustomerZIP',
                'type' => 'hidden',
                'value' => $address->postcode,
            ],
            'CustomerCountry' => [
                'name' => 'CustomerCountry',
                'type' => 'hidden',
                'value' => $address->country,
            ],
            'CustomerPhone' => [
                'name' => 'CustomerPhone',
                'type' => 'hidden',
                'value' => $address->phone,
            ],
            'CustomerEmail' => [
                'name' => 'CustomerEmail',
                'type' => 'hidden',
                'value' => $customer->email,
            ],
        ];

        $new_inputs = [];
        foreach ($inputs as $k => $v) {
            $new_inputs["monri_$k"] = [
                'name' => 'monri_' . $k,
                'type' => 'hidden',
                'value' => $v['value'],
            ];
        }

        $new_inputs['monri_module_name'] = [
            'name' => 'monri_module_name',
            'type' => 'hidden',
            'value' => 'monri',
        ];

        // Correct test?
        $externalOption->setCallToActionText($this->l('Pay using Monri WSPay - Kartično plaćanje'))
            ->setAction($form_url)
            ->setInputs($new_inputs);

        return $externalOption;
    }

    private function updateConfiguration($mode)
    {
        $update_keys = [
            $mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST,
            $mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST,
        ];

        foreach ($update_keys as $key) {
            Configuration::updateValue($key, (string) Tools::getValue($key));
        }
    }

    private function validateConfiguration($mode, $payment_type)
    {
        $mode_uppercase = strtoupper($mode);
        $monri_webpay_authenticity_token = Tools::getValue("MONRI_AUTHENTICITY_TOKEN_$mode_uppercase");
        $monri_webpay_merchant_key = (string) Tools::getValue("MONRI_MERCHANT_KEY_$mode_uppercase");

        $output = null;

        // validating the input
        if ((empty($monri_webpay_merchant_key) || !Validate::isGenericName($monri_webpay_merchant_key)) &&
            ($payment_type == MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY ||
             $payment_type == MonriConstants::PAYMENT_TYPE_MONRI_COMPONENTS ||
             $payment_type == MonriConstants::PAYMENT_TYPE_MONRI_WSPAY )) {
            $output .= $this->displayError($this->l("Invalid Configuration value for Monri Merchant Key/Shop ID $mode"));
        }

        // validating the input
        if ((empty($monri_webpay_authenticity_token) || !Validate::isGenericName($monri_webpay_authenticity_token)) &&
            ($payment_type == MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY ||
             $payment_type == MonriConstants::PAYMENT_TYPE_MONRI_COMPONENTS ||
             $payment_type == MonriConstants::PAYMENT_TYPE_MONRI_WSPAY )) {
            $output .= $this->displayError($this->l("Invalid Configuration value for Monri Api Key/Secret $mode"));
        }

        return $output;
    }

    /**
     * shows the configuration page in the back-end
     *
     * @noinspection PhpUnused
     */
    public function getContent()
    {
        $output = null;

        if (!Tools::isSubmit('submit' . $this->name)) {
            return $output . $this->displayForm();
        } else {
            // get post values
            $mode = (string) Tools::getValue(MonriConstants::KEY_MODE);
            $payment_type = (string) Tools::getValue(MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE);
            $transaction_type = (string) Tools::getValue(MonriConstants::MONRI_TRANSACTION_TYPE);

            if ($mode != MonriConstants::MODE_PROD && $mode != MonriConstants::MODE_TEST) {
                $output .= $this->displayError($this->l("Invalid Mode, expected: prod or test got '$mode'"));

                return $output . $this->displayForm();
            } elseif ($payment_type != MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY &&
                      $payment_type != MonriConstants::PAYMENT_TYPE_MONRI_WSPAY &&
                      $payment_type != MonriConstants::PAYMENT_TYPE_MONRI_COMPONENTS) {
                $output .= $this->displayError($this->l("Invalid Payment Service, expected: Monri WebPay, Monri Components or Monri WSPay got '$payment_type'"));

                return $output . $this->displayForm();
            } elseif ($transaction_type != MonriConstants::TRANSACTION_TYPE_CAPTURE && $transaction_type != MonriConstants::TRANSACTION_TYPE_AUTHORIZE) {
                $output .= $this->displayError($this->l("Invalid Payment Service, expected: capture or authorize got '$transaction_type'"));

                return $output . $this->displayForm();
            } else {
                $validate = $this->validateConfiguration($mode, $payment_type);
                if (!$validate) {
                    $this->updateConfiguration(MonriConstants::MODE_PROD);
                    $this->updateConfiguration(MonriConstants::MODE_TEST);
                    Configuration::updateValue(MonriConstants::KEY_MODE, $mode);
                    Configuration::updateValue(MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE, $payment_type);
                    Configuration::updateValue(MonriConstants::MONRI_TRANSACTION_TYPE, $transaction_type);
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                } else {
                    $output .= $validate;
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
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('General Settings'),
                'image' => '../img/admin/edit.gif',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Monri merchant key/shop id for Test'),
                    'name' => MonriConstants::KEY_MERCHANT_KEY_TEST,
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('If you don\'t know your test merchant key please contact support@monri.com'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Monri merchant key/shop id for Production'),
                    'name' => MonriConstants::KEY_MERCHANT_KEY_PROD,
                    'size' => 20,
                    'required' => true,
                    'lang' => false,
                    'hint' => $this->l('If you don\'t know your production merchant key please contact support@monri.com'),
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
                            'label' => $this->l('Production'),
                        ],
                        [
                            'id' => MonriConstants::MODE_TEST,
                            'value' => MonriConstants::MODE_TEST,
                            'label' => $this->l('Test'),
                        ],
                    ],
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Monri Authenticity token/secret for Test'),
                    'name' => MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST,
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Authenticity-Token please contact support@monri.com'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Monri Authenticity token/secret for Prod'),
                    'name' => MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD,
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('If you don\'t know your Authenticity-Token please contact support@monri.com'),
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Payment gateway service'),
                    'name' => MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE,
                    'class' => 't',
                    'values' => [
                        [
                            'id' => MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY,
                            'value' => MonriConstants::PAYMENT_TYPE_MONRI_WEBPAY,
                            'label' => $this->l('Monri WebPay'),
                        ],
	                    [
		                    'id' => MonriConstants::PAYMENT_TYPE_MONRI_COMPONENTS,
		                    'value' => MonriConstants::PAYMENT_TYPE_MONRI_COMPONENTS,
		                    'label' => $this->l('Monri Components'),
	                    ],
                        [
                            'id' => MonriConstants::PAYMENT_TYPE_MONRI_WSPAY,
                            'value' => MonriConstants::PAYMENT_TYPE_MONRI_WSPAY,
                            'label' => $this->l('Monri WSPay'),
                        ],
                    ],
                    'required' => true,
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Transaction type'),
                    'name' => MonriConstants::MONRI_TRANSACTION_TYPE,
                    'desc'    => $this->l('Needs to be set to action arranged with Monri for gateway to function properly.'),
                    'class' => 't',
                    'values' => [
                        [
                            'id' => MonriConstants::TRANSACTION_TYPE_AUTHORIZE,
                            'value' => MonriConstants::TRANSACTION_TYPE_AUTHORIZE,
                            'label' => $this->l('Authorize'),
                        ],
                        [
                            'id' => MonriConstants::TRANSACTION_TYPE_CAPTURE,
                            'value' => MonriConstants::TRANSACTION_TYPE_CAPTURE,
                            'label' => $this->l('Capture'),
                        ],
                    ],
                    'required' => true,
                    'hint' => $this->l('Needs to be agreed with Monri WSPay'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ];

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
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        if (Tools::isSubmit('submit' . $this->name)) {
            // get settings from post because post can give errors and you want to keep values
            $merchant_key_live = (string) Tools::getValue(MonriConstants::KEY_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = (string) Tools::getValue(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD);

            $mode = (string) Tools::getValue(MonriConstants::KEY_MODE);

            $merchant_key_test = (string) Tools::getValue(MonriConstants::KEY_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = (string) Tools::getValue(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
            $payment_gateway_service_type = (string) Tools::getValue(MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE);
            $transaction_type = (string) Tools::getValue(MonriConstants::MONRI_TRANSACTION_TYPE);
        } else {
            $merchant_key_live = Configuration::get(MonriConstants::KEY_MERCHANT_KEY_PROD);
            $merchant_authenticity_token_live = Configuration::get(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD);

            $mode = Configuration::get(MonriConstants::KEY_MODE);

            $merchant_key_test = Configuration::get(MonriConstants::KEY_MERCHANT_KEY_TEST);
            $merchant_authenticity_token_test = Configuration::get(MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
            $payment_gateway_service_type = Configuration::get(MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE);
            $transaction_type = Configuration::get(MonriConstants::MONRI_TRANSACTION_TYPE);
        }

        // Load current value
        $helper->fields_value[MonriConstants::KEY_MERCHANT_KEY_PROD] = $merchant_key_live;
        $helper->fields_value[MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD] = $merchant_authenticity_token_live;

        $helper->fields_value[MonriConstants::KEY_MERCHANT_KEY_TEST] = $merchant_key_test;
        $helper->fields_value[MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST] = $merchant_authenticity_token_test;
        $helper->fields_value[MonriConstants::KEY_MODE] = $mode;
        $helper->fields_value[MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE] = $payment_gateway_service_type;
        $helper->fields_value[MonriConstants::MONRI_TRANSACTION_TYPE] = $transaction_type;

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
            MonriConstants::KEY_MERCHANT_KEY_PROD,
            MonriConstants::MONRI_PAYMENT_GATEWAY_SERVICE_TYPE,
            MonriConstants::MONRI_TRANSACTION_TYPE,
        ];

        $db = Db::getInstance();

        /*
         * @noinspection SqlWithoutWhere SqlResolve
         */
        return $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` IN ("' . implode('", "', $names) . '") ');
    }

    public static function getMonriWebPayMerchantKey()
    {
        $mode = Configuration::get(MonriConstants::KEY_MODE);

        return Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_KEY_PROD : MonriConstants::KEY_MERCHANT_KEY_TEST);
    }

    public static function getMonriWSPaySecretKey()
    {
        $mode = Configuration::get(MonriConstants::KEY_MODE);

        return Configuration::get($mode == MonriConstants::MODE_PROD ? MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_PROD : MonriConstants::KEY_MERCHANT_AUTHENTICITY_TOKEN_TEST);
    }

    public static function getMonriTransactionStateId()
    {
        // 2 is for capture while 17 is for authorize
        return (Configuration::get(MonriConstants::MONRI_TRANSACTION_TYPE) === 'capture') ? 2 : 17;
    }

	/**
	 * Generate a form for Embedded Payment
	 *
	 * @return string
	 * @throws SmartyException
	 */
	private function generateEmbeddedForm()
	{
		$this->context->smarty->assign([
			'action' => $this->context->link->getModuleLink($this->name, 'components', [], true),
		]);

		return $this->context->smarty->fetch('module:monri/views/templates/front/paymentOptionMonriComponentsForm.tpl');
	}


}
