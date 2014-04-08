{*
/**
 * FreePaymentBox
 * 
 * Module de paiement PayBox(TM) pour Prestashop (TM).
 * 
 * Fourni sans garantie.
 * 
 * @author Sébastien Monterisi   <sebastienmonterisi@yahoo.fr>  https://github.com/SebSept/FreePaymentBox   
 * @author Jean-François MAGNIER <jf.magnier@gmail.com>         https://github.com/lefakir//FreePaymentBox
 * @author ?@?                   <?>                            https://github.com/PrestaMath
 * 
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL v3.0
 */
 
 /*
 * Template pour affichage client du bouton pour basculer vers le paiement en ligne paybox
 */
*}

<p class="payment_module">
        <br><form method="POST" action="{$pbx_url_form}">

{foreach $pbx as $value}
   <input type="hidden" name="{$value@key}" value="{$value}">
{/foreach}
<img src="modules/freepaymentbox/img/cb.gif">
<input type="submit" value="Accéder au paiement sécurisé">
</form>
</p>
