<body onload="document.getElementById('monri-payment-form').submit();">

<form action="{$action}" id="monri-payment-form" method="post">

    <input type="hidden" name="ch_full_name" id="ch_full_name" value="{$monri_ch_full_name}"/>
    <input type="hidden" name="ch_address" id="ch_address" value="{$monri_ch_address}"/>
    <input type="hidden" name="ch_city" id="ch_city" value="{$monri_ch_city}"/>
    <input type="hidden" name="ch_zip" id="ch_zip" value="{$monri_ch_zip}"/>
    <input type="hidden" name="ch_country" id="ch_country" value="{$monri_ch_country}"/>
    <input type="hidden" name="ch_phone" id="ch_phone" value="{$monri_ch_phone}"/>
    <input type="hidden" name="ch_email" id="ch_email" value="{$monri_ch_email}"/>

    <input type="hidden" name="order_info" id="order_info" value="{$monri_order_info}"/>
    <input type="hidden" name="amount" id="amount" value="{$monri_amount}"/></p>
    <input type="hidden" name="order_number" id="order_number" value="{$monri_order_number}"/>
    <input type="hidden" name="currency" id="currency" value="{$monri_currency}"/>
    <input type="hidden" name="transaction_type" id="transaction_type" value="{$monri_transaction_type}"/>
    <input type="hidden" name="number_of_installments" id="number_of_installments" value="{$monri_number_of_installments}"/>
    <input type="hidden" name="cc_type_for_installments" id="cc_type_for_installments"
           value="{$monri_cc_type_for_installments}"/>
    <input type="hidden" name="installments_disabled" id="installments_disabled" value="{$monri_installments_disabled}"/>
    <input type="hidden" name="force_cc_type" id="force_cc_type" value="{$monri_force_cc_type}"/>

    <input type="hidden" name="moto" id="moto" value="{$monri_moto}"/>

    <input type="hidden" name="authenticity_token" id="authenticity_token" value="{$monri_authenticity_token}"/>
    <input type="hidden" name="digest" id="digest" value="{$monri_digest}"/>

{*    TODO: fix language*}
    <input type="hidden" name="language" id="language" value="hr"/>
    <input type="hidden" name="tokenize_pan_until" id="tokenize_pan_until" value="{$monri_tokenize_pan_until}"/>
    <input type="hidden" name="custom_params" id="custom_params" value="{$monri_custom_params}"/>

    <input type="hidden" name="tokenize_pan" id="tokenize_pan" value="{$monri_tokenize_pan}"/>
    <input type="hidden" name="tokenize_pan_offered" id="tokenize_pan_offered" value="{$monri_tokenize_pan_offered}"/>
    <input type="hidden" name="tokenize_brands" id="tokenize_brands" value="{$monri_tokenize_brands}"/>
    <input type="hidden" name="whitelisted_pan_tokens" id="whitelisted_pan_tokens" value="{$monri_whitelisted_pan_tokens}"/>
    <input type="hidden" name="custom_attributes" id="custom_attributes" value="{$monri_custom_attributes}"/>
</form>
<noscript>
    <br>
    <br>
    <div style="text-align: center">
        <h1>Processing your Transaction</h1>
        <p>Please click continue to continue the processing of your transaction.</p>
        <input type="submit" class="button" value="continue"/>
    </div>
</noscript>
</body>