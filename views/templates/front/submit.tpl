<form action="{$action}" id="monri-payment-form" method="post">
    {foreach from=$monri_inputs item=input}
        <input type="hidden" name="{$input.name}" id="{$input.name}" value="{$input.value}"/>
    {/foreach}
</form>

<br>
<br>
<div style="text-align: center">
    <h1>Processing your Transaction</h1>
    <p>Please click continue if your browser doesn't redirect you automatically.</p>
    <input type="submit" class="button" value="continue"/>
</div>

<script>
    window.onload = function() {
        if(document.readyState === 'complete') {
            document.getElementById('monri-payment-form').submit();
        }
    }
</script>
