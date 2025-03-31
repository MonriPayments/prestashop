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
<div id="monri-error-message" style="display: none" class="alert alert-danger">
    error message
</div>

<form id="monri-form" action="{$action}" method="post">
    <div id="card-element">
        <!-- A Monri Component will be inserted here. -->
    </div>
    <div id="card-errors" role="alert"></div>
    <input type="hidden" id="monri-transaction" name="monri-transaction" autocomplete="off" value=""/>
</form>


<script src='{$scriptUrl}'></script>
<script type="text/javascript">
    var monriClientSecret = '{$clientSecret}';
    var monri = Monri('{$authenticityToken}');
    var allowInstallments = '{$allowInstallments}';
    {literal}
    const components = monri.components({"clientSecret": monriClientSecret});
    var style = {invalid: {color: 'red'}};
    // Create an instance of the card Component.
    var card = components.create('card', {style: style, showInstallmentsSelection: allowInstallments});
    {/literal}
    card.mount('card-element');
    card.onChange(function (event) {
        var displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });
    console.log('action: ', '{$action}')
    // See js in modules/paypal/views/templates/bnpl/bnpl-payment-step.tpl
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('#payment-confirmation button').addEventListener('click', async function(event) {
            // https://weblog.west-wind.com/posts/2023/Feb/16/Async-Event-Methods-and-preventDefault-in-JavaScript
            event.preventDefault();
            event.stopPropagation();

            const selectedOption = $('input[name=payment-option]:checked');
            if (selectedOption.attr("data-module-name") === 'monri') {

                customerShippingAddress = prestashop.customer.addresses['{$customerAddressId}'];
                if (!customerShippingAddress.phone) {
                    setErrorMessage('Phone number is required.')
                    return;
                }

                const transactionParams = {
                    address: customerShippingAddress.address1,
                    fullName: customerShippingAddress.firstname + ' ' + customerShippingAddress.lastname,
                    city: customerShippingAddress.city,
                    zip: customerShippingAddress.postcode,
                    phone: customerShippingAddress.phone,
                    country: customerShippingAddress.country,
                    email: prestashop.customer.email
                };

                try {
                    const response = await monri.confirmPayment(card, transactionParams);
                    if (response.error) {
                        setErrorMessage(response.error.message)
                        return;
                    }

                    if (response.result.status === 'approved') {
                        const monriForm = document.getElementById('monri-form');
                        let monriInput = document.getElementById('monri-transaction');
                        monriInput.value = JSON.stringify(response.result);
                        monriForm.appendChild(monriInput);
                        monriForm.submit();
                    } else {
                        setErrorMessage(response.result.status)
                    }

                } catch (error) {
                    setErrorMessage(error)
                }
            }
        });

        function setErrorMessage(message) {
            const errorContainer = document.getElementById('monri-error-message');
            errorContainer.innerText = message;
            errorContainer.style.display = 'block';
        }
    });

</script>