<body>

<form action="{$action}" id="monri-payment-form" method="post">
    <input type="hidden" name="browser_info" id="browser_info" value=""/>
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

<script type="text/javascript">
    (function() {
        function collectBrowserInfo() {
            var screen_width = window && window.screen ? window.screen.width : '';
            var screen_height = window && window.screen ? window.screen.height : '';
            var color_depth = window && window.screen ? window.screen.colorDepth : '';
            var user_agent = window && window.navigator ? window.navigator.userAgent : '';
            var java_enabled = window && window.navigator && typeof navigator.javaEnabled === 'function'
                ? navigator.javaEnabled()
                : false;
            var ip_address = '{$customer_ip|escape:'javascript':'UTF-8'}';

            var language = '';
            if (window && window.navigator) {
                language = window.navigator.language
                    ? window.navigator.language
                    : window.navigator.browserLanguage || '';
            }

            var d = new Date();
            var time_zone_offset = d.getTimezoneOffset();

            return {
                screen_width: screen_width,
                screen_height: screen_height,
                color_depth: color_depth,
                user_agent: user_agent,
                time_zone_offset: time_zone_offset,
                language: language,
                java_enabled: java_enabled,
                http_accept: '*/*',
                http_user_agent: user_agent,
                http_accept_language: language || '*',
                ip: ip_address,
            };
        }

        document.getElementById('browser_info').value = JSON.stringify(collectBrowserInfo());
        document.getElementById('monri-payment-form').submit();
    })();
</script>
</body>