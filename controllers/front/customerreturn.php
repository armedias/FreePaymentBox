<?php

class FreepaymentboxCustomerreturnModuleFrontController extends ModuleFrontController {
	public $display_column_left = false;
	public function initContent() {
		parent::initContent();

                // 
                $status = Tools::getValue('status');
                
                switch ($status){
                    case 'PBX_REFUSE' :
                        $msg = 'Une erreur est survenue lors du paiement';
                        break;
                    case 'PBX_EFFECTUE' :
                        $msg = 'Votre paiement est en cours de traitement';
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