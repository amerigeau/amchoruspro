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

class AMchoruspro extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public $chorus_content;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'amchoruspro';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Arnaud Merigeau';
        $this->controllers = array('payment', 'validation');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('CHORUS_CONTENT'));
        if (isset($config['CHORUS_CONTENT'])) {
            $this->chorus_content = $config['CHORUS_CONTENT'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->paymentName = $this->l('Chorus Pro');
        $this->displayName = $this->l('(AM) Chorus Pro');
        $this->description = $this->l('This module allows you to accept payments by Chorus Pro.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete these details?');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);

        if ((!isset($this->chorus_content) || empty($this->chorus_content))) {
            $this->warning = $this->l('The "content" field must be configured before using this module.');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        $this->extra_mail_vars = array(
            '{chorus_content}' => Configuration::get('CHORUS_CONTENT'),
        );
    }

    public function install()
    {
        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            // compatible PrestaShop 1.7 et supérieur
            return false;
        }

        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn') || !$this->registerHook('actionValidateOrder')){
            return false;
        }

        if (!Configuration::get('CHORUS_OS')) {
            // create custom order state
            $name = array (
                'en' => 'Chorus Pro payment',
                'fr' => 'Chorus Pro paiement',
                // 'de' => 'Chorus Pro zahlung',
                // 'es' => 'Chorus Pro pago'
            );

            $choruspro_state = new OrderState();
            $choruspro_state->name = $this->convertIsoArrayToIdArray($name);
            $choruspro_state->invoice = false;
            $choruspro_state->send_email = false;
            $choruspro_state->module_name = $this->name;
            $choruspro_state->color = '#B4D38A';
            $choruspro_state->unremovable = false;
            $choruspro_state->hidden = false;
            $choruspro_state->logable = false;
            $choruspro_state->delivery = false;
            $choruspro_state->shipped = false;
            $choruspro_state->paid = false;

            if (!$choruspro_state->save() || !Configuration::updateValue('CHORUS_OS', $choruspro_state->id)) {
                return false;
            }

            // add small icon to state
            @copy(
                _PS_MODULE_DIR_.'amchoruspro/views/img/choruspro.gif',
                _PS_IMG_DIR_.'os/'.Configuration::get('CHORUS_OS').'.gif'
            );
        }

        return true;
    }

    public function uninstall()
    {
        return Configuration::deleteByName('CHORUS_CONTENT')
            && Configuration::deleteByName('CHORUS_OS')
            && parent::uninstall()
        ;
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('CHORUS_CONTENT')) {
                $this->_postErrors[] = $this->l('The "content" field is required.');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('CHORUS_CONTENT', Tools::getValue('CHORUS_CONTENT'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
    }

    private function _displayChorusPro()
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->_displayChorusPro();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->chorusproCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->l('Pay by Chorus Pro'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($this->fetch('module:amchoruspro/views/templates/front/payment_infos.tpl'))
                ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/choruspro_s.png'));

        return [$newOption];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        // TODO
        if (in_array($state, array(Configuration::get('CHORUS_OS')))) {
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'shop_name' => $this->context->shop->name,
                'chorus_content' => $this->chorus_content,
                'status' => 'ok',
                'id_order' => $params['order']->id
            ));
            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $this->smarty->assign('reference', $params['order']->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }
        return $this->fetch('module:amchoruspro/views/templates/hook/payment_return.tpl');
    }

    public function chorusproCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Chorus Pro payment details'),
                    'icon' => 'icon-info'
                ),
                'input' => array(
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Mail content sent to customer after payment'),
                        'desc' => $this->l('Give all details to your customer.'),
                        'name' => 'CHORUS_CONTENT',
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = array();

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'CHORUS_CONTENT' => Tools::getValue('CHORUS_CONTENT', Configuration::get('CHORUS_CONTENT')),
        );
    }

    public function getTemplateVars()
    {
        // $cart = $this->context->cart;
        // $total = $this->trans(
        //     '%amount% (tax incl.)',
        //     array(
        //         '%amount%' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
        //     ),
        //     'Modules.Amchoruspro.Admin'
        // );

        $chorus_content_order = Tools::nl2br(Configuration::get('CHORUS_CONTENT'));
        if (!$chorus_content_order) {
            $chorus_content_order = '___________';
        }

        return [
            // 'chorus_Total' => $total,
            'chorus_content_order' => $chorus_content_order,
        ];
    }

    public static function convertIsoArrayToIdArray($array)
    {
        if (!is_array($array) || empty($array)) {
            return array();
        }

        $converted = array();

        foreach (Language::getLanguages(false) as $language) {
            $key = key_exists($language['iso_code'], $array) ? $language['iso_code'] : 'en';

            $converted[$language['id_lang']] = $array[$key];
        }

        return $converted;
    }

    public function hookactionValidateOrder($params)
    {
        $os_id = $params['orderStatus']->id;
        $id_order = $params['order']->id;
        $order = new Order((int)$id_order);

        if (Validate::isLoadedObject($order) && $order->module == $this->name) {
            if ($os_id == Configuration::get('CHORUS_OS')) {
                $customer = new Customer($order->id_customer);

                $mail_vars = array(
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{order_name}' => $order->getUniqReference(),
                    '{chorus_content}' => Tools::nl2br($this->chorus_content),
                );

                Mail::Send(
                    (int)$order->id_lang,
                    'waiting_infos',
                    Mail::l($this->l('Waiting for Chorus Pro details payment'), (int)$order->id_lang),
                    $mail_vars,
                    $customer->email,
                    $customer->firstname.' '.$customer->lastname,
                    null,
                    null,
                    null,
                    null,
                    dirname(__FILE__).'/mails/',
                    false,
                    (int)$order->id_shop
                );
            }
        }
    }
}
