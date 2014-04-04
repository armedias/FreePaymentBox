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


if (!defined('_PS_VERSION_')) {
	exit;
}

/**
 * Classe du module de paiement
 */
class Freepaymentbox extends PaymentModule 
{
    /**
     * @var string stocke le html a afficher dans le formulaire d'administration
     */
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

    /**
     * Affichage sur la page paiement
     * 
     * Genere le formulaire pour la soumission a PayBox
     * 
     * @param array $params
     * @return string   Contenu html à afficher
     */
    public function hookPayment($params)
    {
        $cart = $this->context->cart;
        $pbx = array();
        $config = Configuration::getMultiple($this->pb_config);         //

        foreach ($this->pb_config as $setting_name) {
            if (in_array($setting_name, $this->pb_form)) {
                $pbx[$setting_name] = $config[$setting_name];
            }
        }

        $id_cart = (string) $cart->id;

        // La documentation indique que ce paramètre est obligatoire
        // mais cela doit concerner uniquement l'appel par CGI
        // fonctionne sans et n'est spécifié dans l'exemple de l' "ANNEXE TECHNIQUE : Appel par clé HMAC",
        // ni dans le fichier php d'exemple.
        // n'est probablement destiné qu'à l'appel par CGI
//        $pbx['PBX_MODE'] = '1'; // 1=appel par formulaire html
        $pbx['PBX_TOTAL'] = (string) ($cart->getOrderTotal() * 100);
        
        $pbx['PBX_PORTEUR'] = (string) $this->context->cookie->email;
        $pbx['PBX_TIME'] = date("c");

        $pbx['PBX_CMD'] = (string) $this->context->cookie->id_customer . '_' . $id_cart . '_' . date('YmdHis');   // ref de la commande : plutot de la référence de transaction
        $pbx['PBX_RETOUR'] = "montant:M;ref_cmd:R;autorisation:A;erreur:E;signature:K";        // K doit etre en dernier position
        $pbx['PBX_REPONDRE_A'] = Tools::getShopDomain(true) . __PS_BASE_URI__ . 'modules/freepaymentbox/ipn.php';
        foreach ($this->url_customer as $url_customer) {
            $pbx[$url_customer] = Tools::getShopDomain(true) . __PS_BASE_URI__ . 'index.php?fc=module&module=freepaymentbox&controller=customerreturn&status=' . $url_customer;
        }

        $msg = '';
        foreach ($pbx as $key => $value) {   // calcul du hash sans url encode
            $msg .= ($key == 'PBX_SITE' ? $key . '=' . $value : '&' . $key . '=' . $value);
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
          } */
        $this->context->smarty->assign('pbx', $pbx);

        $this->context->smarty->assign('pbx_url_form', $this->pb_url[$config['MODE_PROD']]);

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
	
        
    /**
     * Admin form / page
     * 
     * @return string html code
     */
    public function getContent()
    {
        // vérifications
        if(!$this->CheckSSL()) {
            $this->_html .= $this->displayError( $this->l('Votre serveur ne dispose des fonctions openssl requises') );
        }
        if(!$this->CheckPublicKey()) {
            $this->_html .= $this->displayError( $this->l('Vous n avez pas renseigné la clé public Paybox') );
        }
        
        // fin si erreurs
        if($this->error) { //  $this->displayError() a mis $this->error à true ( ^^ ! SRP !)
            return $this->_html;
        }
        
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
        
    /**
     * Verification de la validité des données retournées.
     * 
     * D apres la doc la verification se fait ainsi :
     * 
     * 1. Récupérer le contenu de la donnée du type “K” (pour nous 'signature')
     *  note : on a spécifié dans la var PBX_RETOUR du formulaire envoyé que la signature serait stocké dans la variable 'signature'
     * 2. “URL décodée” cette signature, (pour nous : étape non visible, effectué par Tools::getValue()
     * 3. Décoder en base 64 le résultat de l’étape précédente,
     * 4. Décrypter avec la clé publique d'e-transactions le résultat de l’étape précédente,
     * 5. Calculer une empreinte SHA-1 avec les autres données de la variable "PBX_RETOUR",
     * 6. L’empreinte calculée dans l’étape précédente doit être égale au résultat de l’étape 4.
     * 
     * @return boolean
     */
    public static function verification_signature()
    {
        if(!$pub_key = self::getPublic_key()) {
            return false;
        }
        
            $signed_data ='';
            
        // 1. Récupérer le contenu de la donnée du type “K” (pour nous 'signature')
        // 2. “URL décodée” cette signature, (pour nous : étape non visible, effectué par Tools::getValue()
        $signature = Tools::getValue('signature'); // applique le url_decode()
        // 3. Décoder en base 64 le résultat de l’étape précédente,
           $signature = base64_decode($signature);
            
        // concaténation des données a vérifier (PBX_RETOUR sauf la signature)
            foreach ($_GET as $key => $val){
                if ($key !== 'signature') {
                        $signed_data .= '&' . $key . '=' . $val;
                    }
                }
        $signed_data = substr($signed_data, 1); // suppression du premier '&'
        // 4 , 5 et 6 par openssl_verify
        return openssl_verify($signed_data, $signature, $pub_key ) === 1;
    }
                      
    protected static function getPublic_Key()
    {
        $file_content = @file_get_contents(__DIR__.'/pubkey.pem');
        return openssl_pkey_get_public($file_content);
    }
                
    /**
    * Can server check signature received by paybox response ?
    * 
    * @return bool
    */
   protected function CheckSSL()
   {
       return function_exists('openssl_verify');
   }
          
   /**
    * Is paybox public key valid
    * 
    * @return bool
    */
   protected function CheckPublicKey()
   {
       return $this->getPublic_Key() !== false;
        }
}
