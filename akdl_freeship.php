<?php
/**
 * AK DevLab - Free Shipping Bar
 *
 * Shows customers how much is left to free shipping in the cart,
 * the add-to-cart popup and the checkout summary.
 *
 * @author    AK DevLab
 * @copyright 2026 AK DevLab
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Akdl_Freeship extends Module
{
    const CFG = [
        'AKDL_FS_MODE' => 'auto',
        'AKDL_FS_AMOUNT' => '0',
        'AKDL_FS_CART' => '1',
        'AKDL_FS_MODAL' => '1',
        'AKDL_FS_CHECKOUT' => '1',
        'AKDL_FS_ACCENT' => '#16a34a',
        'AKDL_FS_BG' => '#f0fdf4',
        'AKDL_FS_TEXT' => '#14532d',
    ];

    const COLORS = ['AKDL_FS_ACCENT', 'AKDL_FS_BG', 'AKDL_FS_TEXT'];
    const SWITCHES = ['AKDL_FS_CART', 'AKDL_FS_MODAL', 'AKDL_FS_CHECKOUT'];

    public function __construct()
    {
        $this->name = 'akdl_freeship';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'AK DevLab';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('AK DevLab - Free Shipping Bar');
        $this->description = $this->l('Shows customers how much is left to free shipping in the cart, the add-to-cart popup and checkout.');
    }

    public function install()
    {
        foreach (self::CFG as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return parent::install() && $this->registerHook('actionFrontControllerSetMedia');
    }

    public function uninstall()
    {
        foreach (array_keys(self::CFG) as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Free shipping threshold in the default shop currency (0 = not set).
     */
    private function getThreshold()
    {
        if (Configuration::get('AKDL_FS_MODE') === 'custom') {
            return (float) Configuration::get('AKDL_FS_AMOUNT');
        }

        return (float) Configuration::get('PS_SHIPPING_FREE_PRICE');
    }

    private function isColor($value)
    {
        return (bool) preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value);
    }

    private function color($key)
    {
        $value = Configuration::get($key);

        return $this->isColor($value) ? $value : self::CFG[$key];
    }

    public function hookActionFrontControllerSetMedia()
    {
        $threshold = $this->getThreshold();
        if ($threshold <= 0) {
            return;
        }

        $targets = [];
        if (Configuration::get('AKDL_FS_MODAL')) {
            $targets[] = '#blockcart-modal .cart-content';
            $targets[] = '#blockcart-modal .modal-body';
        }
        if (Configuration::get('AKDL_FS_CART')) {
            $targets[] = '.cart-grid-right .cart-summary';
        }
        if (Configuration::get('AKDL_FS_CHECKOUT')) {
            $targets[] = '#js-checkout-summary';
        }
        if (!$targets) {
            return;
        }

        $currency = $this->context->currency;
        if ($currency && Validate::isLoadedObject($currency)) {
            $threshold = (float) Tools::convertPrice($threshold, $currency);
        }

        $language = $this->context->language;
        $locale = !empty($language->locale) ? $language->locale : $language->iso_code;

        Media::addJsDef([
            'akdlFreeship' => [
                'threshold' => round($threshold, 2),
                'currency' => $currency ? $currency->iso_code : 'EUR',
                'locale' => str_replace('_', '-', $locale),
                'targets' => $targets,
                'colors' => [
                    'accent' => $this->color('AKDL_FS_ACCENT'),
                    'bg' => $this->color('AKDL_FS_BG'),
                    'text' => $this->color('AKDL_FS_TEXT'),
                ],
                'textLeft' => $this->l('Only %amount% left to get FREE shipping (from %threshold%).'),
                'textDone' => $this->l('You have FREE shipping!'),
            ],
        ]);

        $this->context->controller->registerStylesheet(
            'akdl-freeship',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 200]
        );
        $this->context->controller->registerJavascript(
            'akdl-freeship',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    /* ------------------------------------------------------------ settings */

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitAkdlFreeship')) {
            $errors = [];
            $mode = Tools::getValue('AKDL_FS_MODE') === 'custom' ? 'custom' : 'auto';
            $amount = str_replace(',', '.', trim((string) Tools::getValue('AKDL_FS_AMOUNT')));
            if ($amount === '') {
                $amount = '0';
            }
            if (!is_numeric($amount) || (float) $amount < 0 || ($mode === 'custom' && (float) $amount <= 0)) {
                $errors[] = $this->l('Enter a free shipping amount greater than 0.');
            }
            foreach (self::COLORS as $key) {
                if (!$this->isColor(Tools::getValue($key))) {
                    $errors[] = $this->l('Colours must be in #RRGGBB format.');
                    break;
                }
            }

            if ($errors) {
                $output .= $this->displayError(implode('<br>', $errors));
            } else {
                Configuration::updateValue('AKDL_FS_MODE', $mode);
                Configuration::updateValue('AKDL_FS_AMOUNT', (string) (float) $amount);
                foreach (self::SWITCHES as $key) {
                    Configuration::updateValue($key, (int) (bool) Tools::getValue($key));
                }
                foreach (self::COLORS as $key) {
                    Configuration::updateValue($key, (string) Tools::getValue($key));
                }
                $output .= $this->displayConfirmation($this->l('Settings saved.'));
            }
        }

        if (Configuration::get('AKDL_FS_MODE') !== 'custom' && (float) Configuration::get('PS_SHIPPING_FREE_PRICE') <= 0) {
            $output .= $this->displayWarning(
                $this->l('No free shipping amount is set in Shipping → Preferences. Set it there or enter your own amount below, otherwise the bar is hidden.')
            );
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $currency = Currency::getDefaultCurrency();
        $yesNo = [
            ['id' => 'on', 'value' => 1, 'label' => $this->l('Yes')],
            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
        ];

        $form = [
            'form' => [
                'legend' => ['title' => $this->l('Settings'), 'icon' => 'icon-truck'],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Free shipping amount'),
                        'name' => 'AKDL_FS_MODE',
                        'class' => 'fixed-width-xxl',
                        'options' => [
                            'query' => [
                                ['id' => 'auto', 'name' => $this->l('Take from Shipping → Preferences')],
                                ['id' => 'custom', 'name' => $this->l('Use my own amount')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Own amount'),
                        'name' => 'AKDL_FS_AMOUNT',
                        'suffix' => $currency ? $currency->sign : '',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Used only with “Use my own amount”. Products total in the default currency.'),
                    ],
                    ['type' => 'switch', 'label' => $this->l('Show in cart'), 'name' => 'AKDL_FS_CART', 'is_bool' => true, 'values' => $yesNo],
                    ['type' => 'switch', 'label' => $this->l('Show in add-to-cart popup'), 'name' => 'AKDL_FS_MODAL', 'is_bool' => true, 'values' => $yesNo],
                    ['type' => 'switch', 'label' => $this->l('Show in checkout summary'), 'name' => 'AKDL_FS_CHECKOUT', 'is_bool' => true, 'values' => $yesNo],
                    ['type' => 'color', 'label' => $this->l('Accent colour'), 'name' => 'AKDL_FS_ACCENT'],
                    ['type' => 'color', 'label' => $this->l('Background colour'), 'name' => 'AKDL_FS_BG'],
                    ['type' => 'color', 'label' => $this->l('Text colour'), 'name' => 'AKDL_FS_TEXT'],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitAkdlFreeship';
        foreach (array_keys(self::CFG) as $key) {
            $helper->fields_value[$key] = Configuration::get($key);
        }

        return $helper->generateForm([$form]);
    }
}
