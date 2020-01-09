<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

/**
 * @since 1.5.0
 */
class AMchorusproValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        $id_customer = $cart->id_customer;
        $id_address_delivery = $cart->id_address_delivery;
        $id_address_invoice = $cart->id_address_invoice;

        if ($id_customer == 0 || $id_address_delivery == 0 || $id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check if payment option still available in case customer changed address before end of checkout
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'amchoruspro') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->trans('This payment method is not available.', array(), 'Modules.AMchoruspro.Shop'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $mailVars =    array(
            '{chorus_content}' => Configuration::get('CHORUS_CONTENT'),
            '{chorus_content_html}' => str_replace("\n", '<br />', Configuration::get('CHORUS_CONTENT')));

        $this->module->validateOrder(
            (int)$cart->id,
            Configuration::get('CHORUS_OS'),
            $total,
            $this->module->paymentName,
            null,
            $mailVars,
            (int)$currency->id,
            false,
            $customer->secure_key
        );
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }
}
