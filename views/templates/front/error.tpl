{extends "$layout"}
{block name="content"}
    <section>
        <h3>{l s='There was an error' mod='monri'}</h3>
        <p class="warning">
            {l s='We have noticed that there is a problem with your order.' mod='monri'}
        </p>
        <p>{l s='Shopping cart id: ' mod='monri'} {$shopping_cart_id}</p>
        {if isset($error_message)}<p>{l s='Error message: ' mod='monri'} {$error_message}</p> {/if}
        {if isset($error_codes)}<p>{l s='Error codes: ' mod='monri'} {$error_codes}</p> {/if}
    </section>
{/block}


