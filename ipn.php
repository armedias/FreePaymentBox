<?php 
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

/**
 * Script appelé par le serveur paybox pour envoyer les réponses de transaction
 * Acceptation/Refus/...
 * 
 * @todo opérer filtrage par adresse ip, puisque sont renseignées dans la doc.
 */

//A l'appel des urls de retour (PBX_EFFECTUE, PBX_REFUSE, PBX_ANNULE et « l’url http
//directe »), ces variables seront concaténées à la fin de la manière suivante :
//http://www.commerce.fr/cgi/verif_pmt.asp?ref=abc12&trans=71256&auto=30258&tarif=2000&abonnement=354341
//&pays=FRA&erreur=00000
//Il vous faudra alors vérifier impérativement le numéro d’autorisation, le code erreur, le montant
//et la signature électronique : si le numéro d’autorisation existe (dans l’exemple précédent il est
//égale à 30258), que le code erreur est égal à « 00000 », que le montant est identique au
//montant d’origine et que la signature électronique est vérifié, cela signifie que le paiement est
//accepté. Pour le cas d’un paiement refusé, le numéro d’autorisation est inexistant (exemple ci-
//dessous). Vous pouvez également utiliser pour cela la variable E.
//http://www.commerce.fr/cgi/verif_pmt.asp?ref=abc12&trans=71256&tarif=2000&pays=FRA&erreur=00105
//
//Par ailleurs, un numéro d’autorisation composé de “XXXXXX” signifie qu’il s’agit d’une
//transaction de tests pour laquelle il n’y a pas eu de demande d’autorisation auprès de la
//banque du commerçant.

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
require_once(dirname(__FILE__) . '/freepaymentbox.php');

/**
 * Retour serveur indique que la transaction est effectuée avec succes
 * @var bool
 */
$success = false;
/**
 * Message enregistré avec la commande
 * @var string
 */
$message;

// variables récupérées dans l'url. Démandées par le param PBX_RETOUR du formulaire
$montant = (int) Tools::getValue('montant');
$ref_cmd =  Tools::getValue('ref_cmd');
$autorisation = Tools::getValue('autorisation');
$erreur = Tools::getValue('erreur');

// extraction des données passées groupées dans le param $ref_cmd
// @todo création et extration devraient être centralisé la classe du module pour limiter les risques d'incohérences
// format : <id_customer>_<id_cart>_<date(YmdHis>
$id_customer = $id_cart = $timestamp = NULL;
list($id_customer,$id_cart,$timestamp) = explode('_',$ref_cmd);

$cart = new Cart($id_cart);

$montant_panier = $cart->getOrderTotal(true) *100;
$pb = new Freepaymentbox();

// si montant cart et paiement égale alors
// code erreur 00000 = ok sinon ko

// check signature
if(!Freepaymentbox::verification_signature()){
    Logger::addLog("Signature banque invalide id_cart = $id_cart" , 4, $erreur);
    throw new Exception('Signature banque invalide');
    exit(); // just in case exception catched and ignored !
    // @todo should we mark the order invalid ?
}

if ($montant >0 && $montant == (int)$montant_panier && $erreur == '00000'){
    $pb->validateOrder(
            $id_cart,           // $id_cart
            _PS_OS_PAYMENT_,    // $id_order_state
            $montant/100, 
            'Paybox', 
            "Paybox autorisation : $autorisation <br>Code $erreur ",
            array('transaction_id' => $ref_cmd),
            null, //$currency_special
            false, // $dont_touch_amount
            $cart->secure_key ? $cart->secure_key : false  // $secure_key - in case there is no secure_key in cart, set to false to validate order anyway
        );
}
else
{
    if ($montant >0 && $erreur == '00000'){     // paiement mais différent du montant du panier 
        $pb->validateOrder(
                $id_cart, 
                _PS_OS_PAYMENT_, 
                $montant/100, 
                'Paybox', 
                "Paybox autorisation : $autorisation <br>Code $erreur",
                array('transaction_id' => $ref_cmd),
                null, //$currency_special
                false, // $dont_touch_amount
                $cart->secure_key ? $cart->secure_key : false  // $secure_key - in case there is no secure_key in cart, set to false to validate order anyway
        );
        Logger::addLog("Retour banque client $id_customer panier $id_cart pour montant $montant <> montant panier $montant_panier" , 2, $erreur);
    }
    else {
        Logger::addLog("Retour banque client $id_customer panier $id_cart pour montant $montant_panier" , 2, $erreur);
    }
}
echo '<html><head></head><body></body></html>';     // doit répondre par une page 'html vide', ça marche comme ça ??? sinon on recoit des mails 'Warning'
