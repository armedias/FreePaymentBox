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
 * Template pour affichage client du bouton pour basculer vers le paiement en ligne paybox (via redirection)
 */
*}

<p class="payment_module">
	<a href="{$link->getModuleLink('freepaymentbox', 'redirect')}" 
           title="{l s='Cliquer ici pour payer par carte bancaie' mod='freepaymentbox'}">
		<img src="{$base_dir_ssl}modules/freepaymentbox/img/cb.gif" alt="{l s='PayBox' mod='freepaymentbox'}" />
		{l s='Payer par carte bancaire' mod='freepaymentbox'}
	</a>
</p>
