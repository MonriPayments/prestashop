<body>

<form action="{$action|escape:'html'}" id="monri-payment-form" method="post">
    {foreach from=$monri_inputs item=input}
        <input type="hidden" name="{$input.name|escape:'html'}" id="{$input.name|escape:'html'}" value="{$input.value|escape:'html'}"/>
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
            var ip_address = '{$customer_ip|default:''|escape:'javascript'}';

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

        {if isset($is_webpay)}
            var browser_info_element = document.createElement('input');
            browser_info_element.type = 'hidden';
            browser_info_element.name = 'browser_info';
            browser_info_element.value = JSON.stringify(collectBrowserInfo());
            document.getElementById('monri-payment-form').appendChild(browser_info_element);
        {/if}

        document.getElementById('monri-payment-form').submit();
    })();
</script>
</body>