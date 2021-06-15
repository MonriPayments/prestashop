<div class="row">
    <div class="col-xs-12 col-md-12">
        <div class="payment_module monri-payment clearfix" style="border: 1px solid #d6d4d4;
            border-radius: 4px;
            color: #333333;
            display: block;
            font-size: 17px;
            font-weight: bold;
            letter-spacing: -1px;
            line-height: 23px;
            padding: 20px 20px;
            position: relative;
            cursor:pointer;
            margin-top: 10px;
            " onclick="monriPayment();">
            <input style="float:left;" id="monri-btn" type="image" name="submit"
                   src="{$monri_path}logo.png" alt=""
                   style="vertical-align: middle; margin-right: 10px; width:57px; height:57px;"/>
            <div style="float:left; margin-left:10px;">
                <span style="margin-right: 10px;">{l s={'Monri'} mod='monri'}</span>
                <span>
                        <ul class="cards">
                            {foreach from=$payment_method_creditcard_logo item=logo}
                                <li>
                                    <img src="{$this_path_paylike}/views/img/{$logo}" title="{$logo}" alt="{$logo}"/>
                                </li>
                            {/foreach}
                        </ul>
                    </span>
                <small style="font-size: 12px; display: block; font-weight: normal; letter-spacing: 1px;">
                    {l s={'Pay using Monri - Kartično plaćanje'} mod='monri'}
                </small>
            </div>
        </div>
    </div>
</div>

<form id="monri-payment-form" method="POST" action="{$action}">
    {foreach from=$monri_inputs item=value key=input}
        <input type="hidden" name="{$input}" id="{$input}" value="{$value}"/>
    {/foreach}
</form>

<script>
    window.onload = function() {
        if(document.readyState === 'complete') {
            window.monriPayment = function() {
                var form = document.getElementById('monri-payment-form');

                if(form) {
                    form.submit();
                }
            }
        }
    }
</script>
