<?php 
//
// confirmation de serveur à serveur du paiement (IPN instant payment notification)
// 
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

$secure_key='';
if (Freepaymentbox::verification_signature()){
    $secure_key = Tools::getValue('signature');
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
        //$bankwire->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $bankwire->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);
        Logger::addLog("Retour banque client $id_customer panier $id_cart pour montant $montant <> montant panier $montant_panier" , 2, $erreur);
    }
    else {
        Logger::addLog("Retour banque client $id_customer panier $id_cart pour montant $montant_panier" , 2, $erreur);
    }
}
echo '<html><head></head><body></body></html>';     // doit répondre par une page 'html vide', ça marche comme ça ??? sinon on recoit des mails 'Warning'
?>