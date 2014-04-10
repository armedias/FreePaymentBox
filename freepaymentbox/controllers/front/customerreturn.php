<?php

/**
 * Controlleur Retour du client sur le site
 * 
 * @author Sébastien Monterisi   <sebastienmonterisi@yahoo.fr>  https://github.com/SebSept/FreePaymentBox   
 * @author ?@?                   <?>                            https://github.com/PrestaMath
 */
class FreepaymentboxCustomerreturnModuleFrontController extends ModuleFrontController
{

    public $display_column_left = false;

    public function initContent()
    {
        parent::initContent();

        // 
        $status = Tools::getValue('status');

        switch ($status) {
            case 'PBX_REFUSE' :
                $msg = 'Une erreur est survenue lors du paiement';
                break;
            case 'PBX_EFFECTUE' :
                $msg = 'Votre paiement est en cours de traitement';
                
                $id_cart_param = (int) Tools::getValue('id_cart');
                $digest_param = Tools::getValue('digest'); 
                $total_param = Tools::getValue('total');
                // param cart reçu
                if($id_cart_param && $digest_param && $total_param)
                {
                    // correspond a un cart valide
                    $cart = new Cart($id_cart_param);
                    if(Validate::isLoadedObject($cart))
                    {
                        // le cart n'a pas fait l'objet d'une commande - retour ipn, sinon, rien
                        if(!$cart->orderExists())
                        {
                            
                            // le cart a la meme clé de sécurité que l'utilisateur courant
                            if($this->context->customer->secure_key === $cart->secure_key)
                            {
                                // verif de la cohérence des digest (pour voir si id_cart et/ ou montant a été modifié)
                                // normalement cart ne plus être modifié puisqu'il a été dupliqué
                                // de plus le digest est calculé avec le montant, donc cela signifie que le montant total n'a pas changé, ce qui a peu d'interet pour un fraudeur ...
                                // si jamais un hack est tout de meme produit (si le gars n'a pas été jusqu'au form final), ça ne fait finalement que passer la commande en status en attente de confirmation de paiement, donc pas grave puisque à la validation finale du paiement, il y reverification des montants.
                                $digest = hash_hmac('MD5', $id_cart_param.$total_param, $cart->secure_key);
                                if($digest === $digest_param)
                                {
                                    // verif de l'existance du status spécial (en attente de confirmation de paiement)
                                    $order_state = new OrderState( Configuration::get('PBX_PENDING_STATUS') );
                                    if(Validate::isLoadedObject($order_state))
                                    {
                                        $payment_module = new Freepaymentbox();

                                        $validate = $payment_module->validateOrder(
                                            $id_cart_param,           // $id_cart
                                            Configuration::get('PBX_PENDING_STATUS'),    // $id_order_state
                                            $cart->getOrderTotal(), 
                                            'Paybox (Freepaymentbox)', 
                                            'Retour client sur le site avec paiement normalement valide (attendre la confirmation finale de la banque)', //$message,
                                            array(), //array('transaction_id' => $param_ref_cmd),
                                            null, //$currency_special
                                            false, // $dont_touch_amount
                                            $cart->secure_key ? $cart->secure_key : false  // $secure_key - in case there is no secure_key in cart, set to false to validate order anyway
                                        );
                                    }
                                }
                            }
                        }
                        
                    }
                    
                }
                break;
            case 'PBX_ANNULE' :
                $msg = 'Votre paiement est annulée';
                break;
            default :
                $msg = 'Une erreur est survenue lors du paiement';
                break;
        }

        $this->context->smarty->assign('msg', $msg);

        $this->setTemplate('pbxcustomerreturn.tpl');
    }

}

?>