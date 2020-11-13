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

<form action="{$action}" id="payment-form" class="monri-payment-form-{$client_secret}"
      method="POST">

    <div style="display: block; min-height: 154px;height: 154px" id="card-element-{$client_secret}">
        <!-- A Monri Component will be inserted here. -->
    </div>
    <!-- Used to display Component errors. -->
    <div id="card-errors" role="alert"></div>
</form>

<script src="{$base_url}/dist/components.js"></script>
{literal}

<script>
    {/literal}
    (function () {
        var authenticityToken = '{$authenticity_token}';
        var clientSecret = '{$client_secret}';
        {literal}
        window.MonriConfig = {
            authenticityToken: authenticityToken,
            clientSecret: clientSecret
        }
    })()
</script>
{/literal}

{literal}
<style>
    {/literal}
    div#card-element-{$client_secret} {literal} iframe {
        min-height: 200px !important;
    }
</style>
{/literal}