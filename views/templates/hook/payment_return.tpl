{*
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
*}

{if $status == 'ok'}
	<p><img src="{$urls.base_url}modules/amchoruspro/views/img/choruspro.png" alt="{l s="Chorus Pro" mod='amchoruspro'}" style="margin-bottom:15px;" height="35" width="120"></p>
	<p>{l s='Your order on %s is complete.' sprintf=[$shop_name] mod='amchoruspro'}
	<p class="alert alert-info">{if $chorus_content}{$chorus_content}{else}___________{/if}</p>
	{if !isset($reference)}
		<p>{l s='Your order number is #%d.' sprintf=[$id_order] mod='amchoruspro'}
	{else}
		<p>{l s='Your order reference is %s.' sprintf=[$reference] mod='amchoruspro'}
	{/if}
	<p>{l s='For any questions or for further information, please contact our' mod='amchoruspro'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department' mod='amchoruspro'}</a>.</p>
	<p>{l s='An email has been sent to you with this information.' mod='amchoruspro'}</p>
{else}
	<p class="alert alert-warning">{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='amchoruspro'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department' mod='amchoruspro'}</a>.</p>
{/if}
