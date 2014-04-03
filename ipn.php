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

 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
require_once(dirname(__FILE__) . '/freepaymentbox.php');


// check ip 194.2.122.158,195.25.7.166,195.101.99.76

// ($_SERVER['REMOTE_ADDR'] == $authorized_ip
// 

// PBX_CMD contient
//"montant:M;ref_cmd:R;autorisation:A;erreur:E";

$montant =    (int)  tools::getValue('montant');
$ref_cmd =      tools::getValue('ref_cmd');
$autorisation =    tools::getValue('autorisation');
$erreur =          tools::getValue('erreur');

//$pbx_cmd = (string)$this->context->cookie->id_customer.'_'.$id_cart.'_'.date('YmdHis');

list($id_customer,$id_cart,$timestamp) = explode('_',$ref_cmd);

$cart = new Cart($id_cart);

$montant_panier = $cart->getOrderTotal(true) *100;
$pb = new Freepaymentbox();

// si montant cart et paiement égale alors
// code erreur 00000 = ok sinon ko
// 
// check hmac signature

// check signature
if(!Freepaymentbox::verification_signature()){
    Logger::addLog("Signature banque invalide id_cart = $id_cart" , 4, $erreur);
    throw new Exception('Signature banque invalide');
    exit(); // just in case exception catched and ignored !
    // @todo should we mark the order invalid ?
}

if ($montant >0 && $montant == (int)$montant_panier && $erreur == '00000'){
    $pb->validateOrder($id_cart, _PS_OS_PAYMENT_, $montant/100, 'Paybox', 
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
        $pb->validateOrder($id_cart, _PS_OS_PAYMENT_, $montant/100, 'Paybox', 
            "Paybox autorisation : $autorisation <br>Code $erreur",
            array('transaction_id' => $ref_cmd)
        );
        Logger::addLog("Retour banque client $id_customer panier $id_cart pour montant $montant <> montant panier $montant_panier" , 2, $erreur);
    }
    else {
        Logger::addLog("Retour banque client $id_customer panier $id_cart pour montant $montant_panier" , 2, $erreur);
    }
}
echo '<html><head></head><body></body></html>';     // doit répondre par une page 'html vide', ça marche comme ça ??? sinon on recoit des mails 'Warning'
