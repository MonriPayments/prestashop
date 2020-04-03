<body onload="document.getElementById('monri-payment-form').submit();">

<form action="{$action}" id="monri-payment-form" method="post">
    {foreach from=$monri_inputs item=input}
        <input type="hidden" name="{$input.name}" id="{$input.name}" value="{$input.value}"/>
    {/foreach}
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