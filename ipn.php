<?php 
/**
 * FreePaymentBox
 * 
 * Module de paiement PayBox(TM) pour Prestashop (TM).
 * 
 * Fourni sans garantie.
 * 
 * @author Sébastien Monterisi   <sebastienmonterisi@yahoo.fr>  https://github.com/SebSept/FreePaymentBox   
 * @author Jean-François MAGNIER <jf.magnier@gmail.com>         https://github.com/lefakir/FreePaymentBox
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

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
require_once(dirname(__FILE__) . '/freepaymentbox.php');

/**
 * Retour serveur indique que la transaction est effectuée avec succes
 * @var bool
 */
$success = true;

/**
 * Message enregistré avec la commande / dans le log
 * @var string
 */
$message;

// ensemble param url (pour debugage/surveillance)
$param_url = '';
foreach($_GET as $k => $v) {
    $param_url .= urldecode($k).'='.urldecode($v).' ';
}

// variables récupérées dans l'url. Démandées par le param PBX_RETOUR du formulaire
$param_montant = (int) Tools::getValue('montant');
$param_ref_cmd =  Tools::getValue('ref_cmd');
$param_autorisation = Tools::getValue('autorisation');
$param_erreur = Tools::getValue('erreur');

// extraction des données passées groupées dans le param $param_ref_cmd
// @todo création et extration devraient être centralisé la classe du module pour limiter les risques d'incohérences
// format : <id_customer>_<id_cart>_<date(YmdHis)>
$id_customer = $id_cart = $date = NULL;
list($id_customer,$id_cart,$date) = explode('_',$param_ref_cmd);
$id_customer = (int) $id_customer;
$id_cart = (int) $id_cart;

$cart = new Cart($id_cart);

// --- verifications ---

// verification que cart retrouvé
if(!Validate::isLoadedObject($cart)){
    Logger::addLog('Paiement Paybox (Freepaymentbox) : Paiement sur Cart inexistant ! '.$param_url , 4);
    echo '<html><head></head><body>Erreur 1</body></html>'; 
    exit(1);
}

// vérification signature
if(!Freepaymentbox::verification_signature()){
    miseEnEchec('Signature banque invalide', 4);
}

// vérification numéro autorisation
if(is_null($param_autorisation))
{
    miseEnEchec('Numéro autorisation nul - Paiement non valide');
}

// vérification code erreur
if($param_erreur != '00000 ')
{
    miseEnEchec("Echec de la transaction. Code erreur $param_erreur");
}

// vérification du montant - montant payé = montant de la commande
if($param_montant != ($cart->getOrderTotal(true) *100) )
{
    miseEnEchec('Montants paiement et panier différents (panier: '.$cart->getOrderTotal(true).' payé: .'.($param_montant/100).')');
}

// indication mode test
// ajout au message qu'il s'agit d'un numéro d'autorisation en mode test
if($param_autorisation === 'XXXXXX')
{
     $message .= 'Paiement fictif ( mode test )';
}

// enregistrement de la commande
if($success) {
    $order_state = Configuration::get('PS_OS_PAYMENT');
    $message .= 'Paiement validé. Paramètres reçus : '.$param_url;
    Logger::addLog($message.' / '.$param_url , 1);
}
else {
    $order_state = Configuration::get('PS_OS_ERROR');
}

$payment_module = new Freepaymentbox();

$payment_module->validateOrder(
        $id_cart,           // $id_cart
        $order_state,    // $id_order_state
        $param_montant/100, 
        'Paybox (Freepaymentbox)', 
        $message,
        array('transaction_id' => $param_ref_cmd),
        null, //$currency_special
        false, // $dont_touch_amount
        $cart->secure_key ? $cart->secure_key : false  // $secure_key - in case there is no secure_key in cart, set to false to validate order anyway
    );


if($success) {  
    // réponse par page html vide 
    echo '<html><head></head><body></body></html>';
} else {
    // réponse par page html avec message erreur (provoque envoi de mail par paybox (non vérifié)) 
   echo '<html><head></head><body>Erreur 2</body></html>'; 
}

/**
 * fonctions locales
 */

/**
 * Mise en echec du paiement
 * 
 * Ne provoque pas l'echec directement, l'indique.
 * 
 * Passe globale $success à False
 * Ajoute param message dans globale $message
 * Log erreur
 * 
 * @global $message
 * @global $success
 * @global $param_url
 * @param string $message
 * @param int    $niveau_erreur niveau d'erreur pour le Logger
 */
function miseEnEchec($p_message, $p_niveau_erreur=2)
{
    global $success, $message, $param_url;
    // modif var globales
    $success = false;
    $message .= $p_message;
    
    // enregistrement dans le log PS
    Logger::addLog($p_message.' / '.$param_url , 4);
}