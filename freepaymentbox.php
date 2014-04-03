<?php

//
// Module FreePaymentBox pour système de paiement en ligne Paybox 
// Pour Prestashop 1.5.2 minimum
// Version SHA512 ne nécessite pas de module CGI
// version 0.01
// A utiliser à vos risques et périls, aucune garantie, ni support n'est assuré.
//


// Récupération clé publique http://www1.paybox.com/telechargements/pubkey.pem


if (!defined('_PS_VERSION_')) {
	exit;
}

class Freepaymentbox extends PaymentModule {
	private $_html = '';
        // url appel paybox classique
        private $pb_url; 
        //private $pb_config_lib = array('PBX_SITE','PBX_RANG','PBX_IDENTIFIANT','PBX_HASH','PBX_DEVISE','SECRET_KEY','MODE_PROD');
        private $pb_config = array('PBX_SITE','PBX_RANG','PBX_IDENTIFIANT','PBX_HASH','PBX_DEVISE','SECRET_KEY','MODE_PROD');
        
        
        private $pb_form  = array('PBX_SITE','PBX_RANG','PBX_IDENTIFIANT','PBX_HASH','PBX_DEVISE');
        private $pb_pay = array('PBX_TOTAL','PBX_CMD','PBX_PORTEUR','PBX_RETOUR','PBX_HASH','PBX_TIME','PBX_HMAC');

        
        private $url_customer = array('PBX_REFUSE','PBX_EFFECTUE','PBX_ANNULE');

        
	public function __construct() {
		$this->name = 'freepaymentbox';
		$this->tab = 'payments_gateways';
		$this->version = '1.0';
		$this->currencies = false;

		parent::__construct();

        // Gestion de l'url Paybox mobile et classique
        // 0 préprod
        // 1 prod principal
        // 2 prod secours
        if (Context::getContext()->getMobileDevice() == FALSE)
        {
            $this->pb_url = array('0' => 'https://tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi',
            '1' => 'https://tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi',
            '2' => 'https://tpeweb1.paybox.com/cgi/MYchoix_pagepaiement.cgi'
            );
        }
        else
        {
            $this->pb_url = array('0' => 'https://tpeweb.paybox.com/cgi/ChoixPaiementMobile.cgi',
            '1' => 'https://tpeweb.paybox.com/cgi/ChoixPaiementMobile.cgi',
            '2' => 'https://tpeweb1.paybox.com/cgi/ChoixPaiementMobile.cgi'
            );
        }

		$this->displayName = $this->l('Freepaymentbox');
		$this->description = $this->l('Free to use and free of charge paybox payment toolkit adaptation');
	}

        public function install() {
		if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn')) {
			return false;
		}
		return true;
	}
        
        private function saveSettings(){
            foreach ($this->pb_config as $setting_name){
                Configuration::updateValue($setting_name,Tools::getValue($setting_name));
            }
        }

        public function hookPayment($params) {
		/* @var $cart Cart */
		$cart = $this->context->cart;
                $pbx = array();
                $config = Configuration::getMultiple($this->pb_config);         //
                
                foreach ($this->pb_config as $setting_name){
                    if ( in_array($setting_name, $this->pb_form)){
                    $pbx[$setting_name] = $config[$setting_name];}
                }
                
                $id_cart= (string)$cart->id;
                
                $pbx['PBX_TOTAL'] = (string)($cart->getOrderTotal()*100);
                $pbx['PBX_PORTEUR'] = (string)$this->context->cookie->email;
                $pbx['PBX_TIME'] = date("c");
                
                $pbx['PBX_CMD'] =  (string)$this->context->cookie->id_customer.'_'.$id_cart.'_'.date('YmdHis');   // ref de la commande : plutot de la référence de transaction
                $pbx['PBX_RETOUR'] =  "montant:M;ref_cmd:R;autorisation:A;erreur:E;signature:K";        // K doit etre en dernier position
                $pbx['PBX_REPONDRE_A'] = Tools::getShopDomain(true).__PS_BASE_URI__.'modules/freepaymentbox/ipn.php';
                foreach ($this->url_customer as $url_customer){
                    $pbx[$url_customer] = Tools::getShopDomain(true).__PS_BASE_URI__.'index.php?fc=module&module=freepaymentbox&controller=customerreturn&status='.$url_customer;
                }
                
                $msg ='';
                foreach ($pbx as $key => $value){   // calcul du hash sans url encode
                    $msg .= ($key == 'PBX_SITE' ? $key.'='.$value : '&'.$key.'='.$value);
                }
                
//                 foreach ($this->url_customer as $url_customer){    // urlencode des urls retour,refuse,annule
//                     $pbx[$url_customer] = urlencode($pbx[$url_customer]);
//                }
//          
                // calcul du hmac
          
                
                // On récupère la clé secrète HMAC (stockée dans une base de données par exemple) et que l’on renseigne dans la variable $keyTest;
               $keyTest = $config['SECRET_KEY'];  // "ABDCDEF";
               
                // Si la clé est en ASCII, On la transforme en binaire
                $binKey = pack("H*", $keyTest);
                // On calcule l’empreinte (à renseigner dans le paramètre PBX_HMAC) grâce à la fonction hash_hmac et // la clé binaire
                // On envoie via la variable PBX_HASH l'algorithme de hachage qui a été utilisé (SHA512 dans ce cas)
                // Pour afficher la liste des algorithmes disponibles sur votre environnement, décommentez la ligne // suivante
                 //print_r(hash_algos());
                $hmac = strtoupper(hash_hmac('sha512', $msg, $binKey));
                // La chaîne sera envoyée en majuscules, d'où l'utilisation de strtoupper()

                 $pbx['PBX_HMAC'] = $hmac;
                 
                /* foreach($pbx as $key => $value){
                     $pbx[$key] = urlencode($pbx[$key]);
                 }*/
                $this->context->smarty->assign('pbx',$pbx);

                $this->context->smarty->assign('pbx_url_form',$this->pb_url[$config['MODE_PROD']]);

		return $this->display(__FILE__, 'payment.tpl'); // si probleme ne pas retourner de tpl pour ne rien afficher
	}
	
	/**
	 *  Page pour le client de retour de la banque
	 * @param array $params
	 */
	public function hookPaymentReturn($params) {
            //PBX_REFUSE,PBX_EFFECTUE,PBX_ANNULE 
            
            $status = Tools::getValue('status','');
            
            switch ($status){
                case 'PBX_EFFECTUE' :       // a priori paiement effectué
                    // verif si commande enregistrée et payée
                    break;
                default :
                    break;
                    // paiement non effectué
            }
            
            // url de retour du client vers le site
            
            
            
		return $this->display(__FILE__, 'payment_return.tpl');
	}
	
        
        
        public function getContent() {
            // verifier si openssl est actif sur le serveur 
		if (Tools::isSubmit('submitFreepaymentbox')) {
			$this->saveSettings();
		}

                $config = Configuration::getMultiple($this->pb_config);
                $this->_html .='MOD_PROD : 0 test 1 production';
                $this->_html .= '<form action="' . $_SERVER['REQUEST_URI']. '" method="post">';
                
                foreach ($this->pb_config as $setting_name){
                    $this->_html .= '<div class="clear">'.$setting_name.
                            '<input type="text" name="'.$setting_name.'" value="'.$config[$setting_name].'">
                             </div>';
                }    
                $this->_html .='<div class="clear"><input type="submit" class="button" name="submitFreepaymentbox" value="'
				. $this->l('Save') . '" /></div>
                                    </form>';
		return $this->_html;
	}
        
        public static function verification_signature(){
            $public_key = file_get_contents('pubkey.pem');
            if ($public_key == false){return false;}
            $signed_data ='';
            
           $signature =   Tools::getValue('signature');        // pas besoin de urldecode
           $signature = base64_decode($signature);
            
            foreach ($_GET as $key => $val){
                if ($key !== 'signature') {
                        $signed_data .= '&' . $key . '=' . $val;
                    }
                }
           $signed_data = substr($signed_data,1);     
                      
           if (openssl_verify( $signed_data, $signature, $public_key ))
                return true;
           else
               return false;
                
        }
}
?>