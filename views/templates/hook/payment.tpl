<p class="payment_module">
        <br><form method="POST" action="{$pbx_url_form}">

{foreach $pbx as $value}
   <input type="hidden" name="{$value@key}" value="{$value}">
{/foreach}
<img src="modules/freepaymentbox/img/cb.gif">
<input type="submit" value="Accéder au paiement sécurisé">
</form>
</p>
