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
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
require_once(dirname(__FILE__) . '/freepaymentbox.php');

/**
 * @var array IPs autorisées
 */
$AUTHORIZED_IP = array('195.101.99.76',' 194.2.122.158', '195.25.7.166', '127.0.0.1');

/**
 * Retour serveur indique que la transaction est effectuée avec succes
 * @var bool
 */
$success = false;

/**
 * Comptage des erreurs
 * Pour déterminer si l'echec du paiement n'est du qu'au code erreur ou pas
 * @todo gérer autrement
 */
$errors_count = 0;

/**
 * Message enregistré avec la commande / dans le log
 * @var string
 */
$message = '';

/**
 * Procéder a l'enregistrement de la commande / changement de status ?
 * @var bool
 */
$process = true;

// filtrage par adresse IP
$ip = $_SERVER['REMOTE_ADDR'];

//if(!in_array($ip, $AUTHORIZED_IP)) {
//    header('Unauthorized', true, 401);
//    exit();
//}

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

// --- verifications internes ---

// verification que cart retrouvé
if(!Validate::isLoadedObject($cart)){
    miseEnEchec('Paiement sur Cart inexistant ! ', 4);
}

// --- vérification validité du paiement --- (cf doc paybox)

// vérification signature
if(!Freepaymentbox::verification_signature()){
    miseEnEchec('Signature banque invalide', 4);
}

// vérification numéro autorisation
// au cas ou il serait renvoyé
if(is_null($param_autorisation) || strlen($param_autorisation) < 4)
{
    miseEnEchec('Numéro autorisation nul/vide - Paiement non valide');
}

// vérification code erreur
if($param_erreur !== '00000')
{
    miseEnEchec("Echec de la transaction. Le client n'a pas payé. Code erreur $param_erreur");
}

// indication mode test
// ajout au message qu'il s'agit d'un numéro d'autorisation en mode test
if($param_autorisation === 'XXXXXX')
{
     $message .= 'Paiement fictif ( mode test )';
}

// traitement de l'existance d'une commande déjà placée avec ce cart, si données reçue valides

// commande déjà enregistrée - cas existant ?
if($cart->OrderExists())
{
    $order = new Order( Order::getOrderByCartId($cart->id) );
    
    // verif instanciation order
    if(!Validate::isLoadedObject($order)) 
    {
        $message .= 'Retour serveur (3) sur panier/commande existante. Commande non chargée. ';
        miseEnEchec($message, 4);
        $process = false;
    }
    else //if($errors_count == 0)
    {
        // verifier montant de la commande avant changement de status

        if($cart->getOrderTotal()*100 != $param_montant )
        {
            $message .= 'Retour serveur (4) sur panier/commande existante. Montants incohérents. '
                    . 'cart: '.$cart->getOrderTotal()*100
                    . 'param_montant:  '.$param_montant;
            miseEnEchec($message);
        }
    }
    // si $param_erreur correspond a l'erreur 'paiement déjà effectué'), on ignore cette réponse serveur
    // normalement, ce cas ne se produit pas
    if($param_erreur === '00015')
    {
        $process = false;
        $success = true;
        Logger::addLog($message.' / '.$param_url); 
//        $message .= ' Retour serveur (1) sur panier/commande existante. param_erreur=00015. ';
//        miseEnEchec($message);
    }
    
    // au final, erreurs ?
    if($errors_count == 0){
        $success = true;
        $message .= ' Paiement validé. Paramètres reçus : '.$param_url;
        Logger::addLog($message.' / '.$param_url , 1); 
    }
            
    
    // changement de status de la commande
    if($process) {
        if($success) {
            $order_state = Configuration::get('PS_OS_PAYMENT');
        }
        else {
            $order_state = Configuration::get('PS_OS_ERROR');
        }

        Context::getContext()->employee = new Employee(1); // dirty hack - sinon mail alert pete un plomb :/
        // ajout d'un order history
        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->changeIdOrderState( $order_state, (int)($order->id)); 
        $history->add(); // mise a jour de l'order

        // ajout d'un message
        $message .= ' Paiement validé. Paramètres reçus : '.$param_url;
        $order_message = new Message();
        $order_message->message = $message;
        $order_message->id_order = (int)$order->id;
        $order_message->save();
    }

    // affichage
    if($success) {
        echo '<html><head></head><body></body></html>';
    }
    else {
        echo '<html><head></head><body>Erreur 5</body></html>';   
    }
}
else // commande non existante : enregistrement de la commande
{
    // vérification du montant - montant payé = montant de la commande
    if( $param_montant != (int)($cart->getOrderTotal(true)*100) )
    {
        miseEnEchec('Montants paiement et panier/commande différents (panier: '.$cart->getOrderTotal(true).' payé: .'.($param_montant/100).')');
    }

    if($errors_count == 0){
        $success = true;
        $order_state = Configuration::get('PS_OS_PAYMENT');
        $message .= ' Paiement validé. Paramètres reçus : '.$param_url;
        Logger::addLog($message.' / '.$param_url , 1); 
    }
    else {
        $order_state = Configuration::get('PS_OS_ERROR');
        $message .= ' Paiement non validé. Paramètres reçus : '.$param_url;
        Logger::addLog($message.' / '.$param_url , 4); 
    }

    // enregistrement commande
    $payment_module = new Freepaymentbox();

    $validate = $payment_module->validateOrder(
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
    
    // affichage html
    if(!$validate)
    {
        miseEnEchec('Echec ValidateOrder', 5);
        echo '<html><head></head><body>Erreur 2</body></html>'; 
        exit();
    }
    elseif(!$success) 
    {
        echo '<html><head></head><body>Erreur 0</body></html>'; 
        exit();
    }
    else {
        echo '<html><head></head><body></body></html>';
    }
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
 * @global $errors_count
 * @global $param_url
 * @param string $message
 * @param int    $niveau_erreur niveau d'erreur pour le Logger
 */
function miseEnEchec($p_message, $p_niveau_erreur=2)
{
    global  $success, 
            $message, 
            $errors_count, 
            $param_url;
    
    // modif var globales
    $message .= $p_message;
    // non necessaire depuis que correspond aux valeurs initiales
    $success = false;
    $errors_count++;
    
    // enregistrement dans le log PS
    Logger::addLog($p_message.' / '.$param_url , 4);
}

/*
 * DEBUG 

effacement commande

DELETE FROM `ps_orders` WHERE `id_order` > 427;
DELETE FROM ps_order_history WHERE `id_order` > 427;
DELETE FROM ps_order_carrier WHERE `id_order` > 427;
DELETE FROM ps_order_detail WHERE `id_order` > 427;
DELETE FROM ps_order_invoice WHERE `id_order` > 427;
DELETE FROM ps_order_invoice_payment WHERE `id_order` > 427;

URL ipn : 
http://localhost/pro/alabrideslezards/prestashop/modules/freepaymentbox/ipn.php?montant=2770&ref_cmd=415_413_20140402144434&autorisation=123456&erreur=00000&signature=IglGG5PidlEijvgnwYxpAbWcG3gq6iae7tF4E%2F1nBc4hm%2FwrgN4njZZFvsnaDwPk7JyADyP4SIeFw%2FLkLw2n7ecb9%2FLf7rAkLijOV5cGLp%2FU5xDpfW1nCYFLMGUq8ds2aZWDMVD9fY%2BLw4c1PLpvQpjIR3xdozST3YRPKUFZNDs%3D
 * 
 * URL retour client :
 * 
http://localhost/pro/alabrideslezards/prestashop/index.php?fc=module&module=freepaymentbox&controller=customerreturn&status=PBX_EFFECTUE&id_cart=413&total=2770&digest=?
 * 
http://localhost/pro/alabrideslezards/prestashop/index.php?fc=module&module=freepaymentbox&controller=customerreturn&status=PBX_EFFECTUE&id_cart=414&total=2860&digest=d1d9fe2a985e9057f24da4a85416ce2c

 */