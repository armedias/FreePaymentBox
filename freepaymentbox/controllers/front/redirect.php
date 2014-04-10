<?php

/**
 * FreePaymentBox
 * 
 * Module de paiement PayBox(TM) pour Prestashop (TM).
 * 
 * Fourni sans garantie.
 * 
 * @author Sébastien Monterisi   <sebastienmonterisi@yahoo.fr>  https://github.com/SebSept/FreePaymentBox   
 * 
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GPL v3.0
 */
class FreepaymentboxRedirectModuleFrontController extends ModuleFrontController
{

    public $display_column_left = false;
    public $display_column_right = false;
    protected $display_footer = false;
    protected $display_header = true;
    
    public function initContent()
    {
        parent::initContent();
   
        $module = new Freepaymentbox();
        
        $duplicate_result = $this->context->cart->duplicate();
        // création de la commande avec status en cours de paiement
        $module->preValidateOrder();
        
        // duplication du panier (copié avant commande)
        if($duplicate_result['success'])
        {
            $this->context->cart = $duplicate_result['cart'];
            $this->context->cookie->id_cart = $this->context->cart->id;
        }
        
        // formulaire
        $this->context->smarty->assign('params', $module->getFormFields());
        $this->context->smarty->assign('pb_url', $module->getFormUrl());
        
        $this->setTemplate('redirect.tpl');
    }

}
