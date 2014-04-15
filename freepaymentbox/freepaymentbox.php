<?php
/**
 * FreePaymentBox
 * 
 * Module de paiement PayBox(TM) pour Prestashop (TM).
 * http://prestashop.seb7.fr/
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
 * 
 * @todo désinstallation : supprimer le status créé à l'installation
 */
class Freepaymentbox extends PaymentModule 
{
    /**
     * @var string stocke le html a afficher dans le formulaire d'administration
     */
    private $_html = '';
    
    // url appel paybox classique
    private $pb_url; 
    
    /**
     * Variables de base du module
     * 
     * @inheritdoc
     * @var string 
     */
    public $name = 'freepaymentbox';
    public $tab = 'payments_gateways';
    public $version = '1.1';
    public $currencies = false;

    /**
     * Variable configurables (en admin)
     * Les variables préfixés PBX seront incluses dans le formulaire de paiement soumis au site
     * a condition de figurer dans 
     * 
     * PBX_DEVISE : identifiant de la devise - @see http://www.iso.org/iso/fr/home/standards/currency_codes.htm
     * @todo documenter autres variables
     * 
     * @var array 
     */
    private $pb_config = array('PBX_SITE','PBX_RANG','PBX_IDENTIFIANT','PBX_HASH','PBX_DEVISE','SECRET_KEY','MODE_PROD');
        
    /**
     * @todo doc
     * @var type 
     */
    private $pb_form  = array('PBX_SITE','PBX_RANG','PBX_IDENTIFIANT','PBX_HASH','PBX_DEVISE');
        
    // commenté : inutilisé !
    // private $pb_pay = array('PBX_TOTAL','PBX_CMD','PBX_PORTEUR','PBX_RETOUR','PBX_HASH','PBX_TIME','PBX_HMAC');

        
    private $url_customer = array('PBX_REFUSE','PBX_EFFECTUE','PBX_ANNULE');

        
    public function __construct($name = null, Context $context = null) 
    {
        parent::__construct($name, $context);

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
        $this->description = $this->l('Module pour le paiement par PayBox');
    }

    /**
     * Installation 
     * 
     * @return boolean
     */
    public function install() 
    {
        return parent::install() 
                && $this->registerHook('payment') 
                && $this->registerHook('paymentReturn')
                && $this->addOrderState();
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
        return $this->display(__FILE__, 'payment.tpl'); // si probleme ne pas retourner de tpl pour ne rien afficher
    }

    /**
     * Données a ajouter dans le formulaire envoyé a PayBox
     * 
     * @return array
     */
    public function getFormFields()
    {
                // tableau des variables du formulaire soumis a Paybox
        $pbx = array();
        // panier , contient les informations pour réaliser la commande
        $cart = $this->context->cart;
        
        $config = Configuration::getMultiple($this->pb_config);

        // inclusion dans le formulaire des variables configurables en admin
        // seules les variables présentes dans $pb_form sont incluses
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
        $pbx['PBX_MODE'] = '1'; // 1=appel par formulaire html
        $pbx['PBX_TOTAL'] = (string) ($cart->getOrderTotal() * 100);
        // Adresse email de l’acheteur.
        $pbx['PBX_PORTEUR'] = (string) $this->context->customer->email;
        // date , obligatoire avec la methode par clé HMAC
        // formaté ISO8601, correspond à 'c' en php - http://www.php.net/manual/fr/function.date.php
        $pbx['PBX_TIME'] = date("c");
        // reférence. La commande est encore inexistante. Réference est donc <id_customer>_<id_cart>_<date(YmdHis)>
        // @todo centratliser (cf extraction dans ipn.php)
        $pbx['PBX_CMD'] = (string) $this->context->customer->id . '_' . $id_cart . '_' . date('YmdHis');
        // valeurs souhaitées en retour, 22 données possibles, voir doc paybox
        $pbx['PBX_RETOUR'] = "montant:M;ref_cmd:R;autorisation:A;erreur:E;signature:K";        // K doit etre en dernier position
        // url réponse serveur
        $pbx['PBX_REPONDRE_A'] = Tools::getShopDomain(true) . __PS_BASE_URI__ . 'modules/freepaymentbox/ipn.php';
        
        // 'PBX_REFUSE','PBX_EFFECTUE','PBX_ANNULE' - cf $url_customer
        $pbx['PBX_REFUSE'] = Tools::getShopDomain(true) . __PS_BASE_URI__ . 'index.php?fc=module&module=freepaymentbox&controller=customerreturn&status=PBX_REFUSE';
        
        $pbx['PBX_ANNULE'] = Tools::getShopDomain(true) . __PS_BASE_URI__ . 'index.php?fc=module&module=freepaymentbox&controller=customerreturn&status=PBX_ANNULE';
        
//        $digest = hash_hmac('MD5', $id_cart.$pbx['PBX_TOTAL'], $cart->secure_key);
        $pbx['PBX_EFFECTUE'] = Tools::getShopDomain(true) . __PS_BASE_URI__ . 'index.php?fc=module&module=freepaymentbox&controller=customerreturn&status=PBX_EFFECTUE'  ;
         
        $msg = '';
        foreach ($pbx as $key => $value) {   // calcul du hash sans url encode
            $msg .= ($key == 'PBX_SITE' ? $key . '=' . $value : '&' . $key . '=' . $value);
        }

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
        
        return $pbx;
    }
    
    /**
     * Url de soumission paybox
     * 
     * @todo MODE_PROB est vraiment moche!
     * 
     * @return string
     */
    public function getFormUrl()
    {
        return  $this->pb_url[Configuration::get('MODE_PROD')];
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
            $this->_html .= $this->displayError( $this->l('Vous n avez pas renseigné la clé public Paybox ou elle est invalide (consultez le log)') );
        }
        
        // fin si erreurs
        if(isset($this->error) && $this->error) { //  $this->displayError() a mis $this->error à true ( ^^ ! SRP !)
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
     
    /**
     * Clé publique extraite du certificat passé en paramètre
     * 
     * @return mixed resource|false
     */
    protected static function getPublic_Key()
    {
        $instance = new self;
        $file_content = @file_get_contents($instance->getLocalPath().'pubkey.pem');
        if(!$file_content)
        {
            Logger::addLog('Impossible de trouver la cle publique '.$instance->getLocalPath().'pubkey.pem', 3, null, null, null, true);
            return false;
        }
        
        $cle = openssl_pkey_get_public($file_content);
        if(!$cle) {
            Logger::addLog('Impossible d extraire la clé du certificat, votre fichier '.$instance->getLocalPath().'pubkey.pem est invalide.', 3, null, null, null, true);
            return false;
        }
        return $cle;
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
    * Créer la commande avec status 'en cours de paiement...'
    * 
    * @return bool Order validation success
    */
   public function preValidateOrder()
   {
       $cart = $this->context->cart;
       $order_state_id = Configuration::get('PBX_PENDING_STATUS');
       $message = 'Prévalidation de la commande (redirection du client vers le paiement)';
       
       return $this->validateOrder(
            $cart->id,              // $id_cart
            $order_state_id,        // $id_order_state
            $cart->getOrderTotal(), // montant
            'Paybox',               // nom module de paiement
            $message,
            array(),
            null,                   //$currency_special
            false,                  // $dont_touch_amount
            $cart->secure_key ? $cart->secure_key : false  // $secure_key - in case there is no secure_key in cart, set to false to validate order anyway
        );
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
    
    /**
     * Ajout du status 'En attente de confirmation de paiement (Freepaymentbox)'
     * 
     * - Créer le status
     * - Stock id du status créé dans config PBX_PENDING_STATUS
     * 
     * Pour permettre la création de la commande dès le retour du client sur le site
     * 
     * @return bool
     */
    private function addOrderState()
    {
        $os = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
        $os->name = 'Paiement en cours (paybox) ...';
        $os->send_email = false;
	$os->module_name = $this->name;
        $os->invoice = false;
        $os->color = 'RoyalBlue';
        $os->unremovable = true;
        $os->logable = false;
        $os->delivery = false;
        $os->hidden = false;
        $os->shipped = false;
        $os->paid = false;
	$os->deleted = false;
        
        if($os->save()) {
            return Configuration::updateValue('PBX_PENDING_STATUS',  $os->id);
        }
        
        return false;
    }
    
    /**
     * Copie quasi identique complète de la fonction de PaymentModule
     * Seule différence : n'envoit pas mail si le status est PBX_PENDING_STATUS, cf 'seb'
     * 
    * @inheritdoc 
     * @author prestashop
    */
    public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown',
            $message = null, $extra_vars = array(), $currency_special = null, $dont_touch_amount = false,
            $secure_key = false, Shop $shop = null)
    {
            $this->context->cart = new Cart($id_cart);
            $this->context->customer = new Customer($this->context->cart->id_customer);
            $this->context->language = new Language($this->context->cart->id_lang);
            $this->context->shop = ($shop ? $shop : new Shop($this->context->cart->id_shop));
            ShopUrl::resetMainDomainCache();

            $id_currency = $currency_special ? (int)$currency_special : (int)$this->context->cart->id_currency;
            $this->context->currency = new Currency($id_currency, null, $this->context->shop->id);
            if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery')
                    $context_country = $this->context->country;

            $order_status = new OrderState((int)$id_order_state, (int)$this->context->language->id);
            if (!Validate::isLoadedObject($order_status))
                    throw new PrestaShopException('Can\'t load Order state status');

            if (!$this->active)
                    die(Tools::displayError());
            // Does order already exists ?
            if (Validate::isLoadedObject($this->context->cart) && $this->context->cart->OrderExists() == false)
            {
                    if ($secure_key !== false && $secure_key != $this->context->cart->secure_key)
                            die(Tools::displayError());

                    // For each package, generate an order
                    $delivery_option_list = $this->context->cart->getDeliveryOptionList();
                    $package_list = $this->context->cart->getPackageList();
                    $cart_delivery_option = $this->context->cart->getDeliveryOption();

                    // If some delivery options are not defined, or not valid, use the first valid option
                    foreach ($delivery_option_list as $id_address => $package)
                            if (!isset($cart_delivery_option[$id_address]) || !array_key_exists($cart_delivery_option[$id_address], $package))
                                    foreach ($package as $key => $val)
                                    {
                                            $cart_delivery_option[$id_address] = $key;
                                            break;
                                    }

                    $order_list = array();
                    $order_detail_list = array();
                    $reference = Order::generateReference();
                    $this->currentOrderReference = $reference;

                    $order_creation_failed = false;
                    $cart_total_paid = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(true, Cart::BOTH), 2);

                    foreach ($cart_delivery_option as $id_address => $key_carriers)
                            foreach ($delivery_option_list[$id_address][$key_carriers]['carrier_list'] as $id_carrier => $data)
                                    foreach ($data['package_list'] as $id_package)
                                    {
                                            // Rewrite the id_warehouse
                                            $package_list[$id_address][$id_package]['id_warehouse'] = (int)$this->context->cart->getPackageIdWarehouse($package_list[$id_address][$id_package], (int)$id_carrier);
                                            $package_list[$id_address][$id_package]['id_carrier'] = $id_carrier;
                                    }
                    // Make sure CarRule caches are empty
                    CartRule::cleanCache();

                    foreach ($package_list as $id_address => $packageByAddress)
                            foreach ($packageByAddress as $id_package => $package)
                            {
                                    $order = new Order();
                                    $order->product_list = $package['product_list'];

                                    if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery')
                                    {
                                            $address = new Address($id_address);
                                            $this->context->country = new Country($address->id_country, $this->context->cart->id_lang);
                                    }

                                    $carrier = null;
                                    if (!$this->context->cart->isVirtualCart() && isset($package['id_carrier']))
                                    {
                                            $carrier = new Carrier($package['id_carrier'], $this->context->cart->id_lang);
                                            $order->id_carrier = (int)$carrier->id;
                                            $id_carrier = (int)$carrier->id;
                                    }
                                    else
                                    {
                                            $order->id_carrier = 0;
                                            $id_carrier = 0;
                                    }

                                    $order->id_customer = (int)$this->context->cart->id_customer;
                                    $order->id_address_invoice = (int)$this->context->cart->id_address_invoice;
                                    $order->id_address_delivery = (int)$id_address;
                                    $order->id_currency = $this->context->currency->id;
                                    $order->id_lang = (int)$this->context->cart->id_lang;
                                    $order->id_cart = (int)$this->context->cart->id;
                                    $order->reference = $reference;
                                    $order->id_shop = (int)$this->context->shop->id;
                                    $order->id_shop_group = (int)$this->context->shop->id_shop_group;

                                    $order->secure_key = ($secure_key ? pSQL($secure_key) : pSQL($this->context->customer->secure_key));
                                    $order->payment = $payment_method;
                                    if (isset($this->name))
                                            $order->module = $this->name;
                                    $order->recyclable = $this->context->cart->recyclable;
                                    $order->gift = (int)$this->context->cart->gift;
                                    $order->gift_message = $this->context->cart->gift_message;
                                    $order->mobile_theme = $this->context->cart->mobile_theme;
                                    $order->conversion_rate = $this->context->currency->conversion_rate;
                                    $amount_paid = !$dont_touch_amount ? Tools::ps_round((float)$amount_paid, 2) : $amount_paid;
                                    $order->total_paid_real = 0;

                                    $order->total_products = (float)$this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);
                                    $order->total_products_wt = (float)$this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);

                                    $order->total_discounts_tax_excl = (float)abs($this->context->cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
                                    $order->total_discounts_tax_incl = (float)abs($this->context->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
                                    $order->total_discounts = $order->total_discounts_tax_incl;

                                    $order->total_shipping_tax_excl = (float)$this->context->cart->getPackageShippingCost((int)$id_carrier, false, null, $order->product_list);
                                    $order->total_shipping_tax_incl = (float)$this->context->cart->getPackageShippingCost((int)$id_carrier, true, null, $order->product_list);
                                    $order->total_shipping = $order->total_shipping_tax_incl;

                                    if (!is_null($carrier) && Validate::isLoadedObject($carrier))
                                            $order->carrier_tax_rate = $carrier->getTaxesRate(new Address($this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));

                                    $order->total_wrapping_tax_excl = (float)abs($this->context->cart->getOrderTotal(false, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
                                    $order->total_wrapping_tax_incl = (float)abs($this->context->cart->getOrderTotal(true, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
                                    $order->total_wrapping = $order->total_wrapping_tax_incl;

                                    $order->total_paid_tax_excl = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(false, Cart::BOTH, $order->product_list, $id_carrier), 2);
                                    $order->total_paid_tax_incl = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(true, Cart::BOTH, $order->product_list, $id_carrier), 2);
                                    $order->total_paid = $order->total_paid_tax_incl;

                                    $order->invoice_date = '0000-00-00 00:00:00';
                                    $order->delivery_date = '0000-00-00 00:00:00';

                                    // Creating order
                                    $result = $order->add();

                                    if (!$result)
                                            throw new PrestaShopException('Can\'t save Order');

                                    // Amount paid by customer is not the right one -> Status = payment error
                                    // We don't use the following condition to avoid the float precision issues : http://www.php.net/manual/en/language.types.float.php
                                    // if ($order->total_paid != $order->total_paid_real)
                                    // We use number_format in order to compare two string
                                    if ($order_status->logable && number_format($cart_total_paid, 2) != number_format($amount_paid, 2))
                                            $id_order_state = Configuration::get('PS_OS_ERROR');

                                    $order_list[] = $order;

                                    // Insert new Order detail list using cart for the current order
                                    $order_detail = new OrderDetail(null, null, $this->context);
                                    $order_detail->createList($order, $this->context->cart, $id_order_state, $order->product_list, 0, true, $package_list[$id_address][$id_package]['id_warehouse']);
                                    $order_detail_list[] = $order_detail;

                                    // Adding an entry in order_carrier table
                                    if (!is_null($carrier))
                                    {
                                            $order_carrier = new OrderCarrier();
                                            $order_carrier->id_order = (int)$order->id;
                                            $order_carrier->id_carrier = (int)$id_carrier;
                                            $order_carrier->weight = (float)$order->getTotalWeight();
                                            $order_carrier->shipping_cost_tax_excl = (float)$order->total_shipping_tax_excl;
                                            $order_carrier->shipping_cost_tax_incl = (float)$order->total_shipping_tax_incl;
                                            $order_carrier->add();
                                    }
                            }

                    // The country can only change if the address used for the calculation is the delivery address, and if multi-shipping is activated
                    if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery')
                            $this->context->country = $context_country;

                    // Register Payment only if the order status validate the order
                    if ($order_status->logable)
                    {
                            // $order is the last order loop in the foreach
                            // The method addOrderPayment of the class Order make a create a paymentOrder
                            //     linked to the order reference and not to the order id
                            if (isset($extra_vars['transaction_id']))
                                    $transaction_id = $extra_vars['transaction_id'];
                            else
                                    $transaction_id = null;

                            if (!$order->addOrderPayment($amount_paid, null, $transaction_id))
                                    throw new PrestaShopException('Can\'t save Order Payment');
                    }

                    // Next !
                    $only_one_gift = false;
                    $cart_rule_used = array();
                    $products = $this->context->cart->getProducts();
                    $cart_rules = $this->context->cart->getCartRules();

                    // Make sure CarRule caches are empty
                    CartRule::cleanCache();

                    foreach ($order_detail_list as $key => $order_detail)
                    {
                            $order = $order_list[$key];
                            if (!$order_creation_failed && isset($order->id))
                            {
                                    if (!$secure_key)
                                            $message .= '<br />'.Tools::displayError('Warning: the secure key is empty, check your payment account before validation');
                                    // Optional message to attach to this order
                                    if (isset($message) & !empty($message))
                                    {
                                            $msg = new Message();
                                            $message = strip_tags($message, '<br>');
                                            if (Validate::isCleanHtml($message))
                                            {
                                                    $msg->message = $message;
                                                    $msg->id_order = intval($order->id);
                                                    $msg->private = 1;
                                                    $msg->add();
                                            }
                                    }

                                    // Insert new Order detail list using cart for the current order
                                    //$orderDetail = new OrderDetail(null, null, $this->context);
                                    //$orderDetail->createList($order, $this->context->cart, $id_order_state);

                                    // Construct order detail table for the email
                                    $products_list = '';
                                    $virtual_product = true;

                                    foreach ($order->product_list as $key => $product)
                                    {
                                            $price = Product::getPriceStatic((int)$product['id_product'], false, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null), 6, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
                                            $price_wt = Product::getPriceStatic((int)$product['id_product'], true, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null), 2, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});

                                            $customization_quantity = 0;
                                            $customized_datas = Product::getAllCustomizedDatas((int)$order->id_cart);
                                            if (isset($customized_datas[$product['id_product']][$product['id_product_attribute']]))
                                            {
                                                    $customization_text = '';
                                                    foreach ($customized_datas[$product['id_product']][$product['id_product_attribute']][$order->id_address_delivery] as $customization)
                                                    {
                                                            if (isset($customization['datas'][Product::CUSTOMIZE_TEXTFIELD]))
                                                                    foreach ($customization['datas'][Product::CUSTOMIZE_TEXTFIELD] as $text)
                                                                            $customization_text .= $text['name'].': '.$text['value'].'<br />';

                                                            if (isset($customization['datas'][Product::CUSTOMIZE_FILE]))
                                                                    $customization_text .= sprintf(Tools::displayError('%d image(s)'), count($customization['datas'][Product::CUSTOMIZE_FILE])).'<br />';
                                                            $customization_text .= '---<br />';
                                                    }

                                                    $customization_text = Tools::rtrimString($customization_text, '---<br />');

                                                    $customization_quantity = (int)$product['customization_quantity'];
                                                    $products_list .=
                                                    '<tr style="background-color: '.($key % 2 ? '#DDE2E6' : '#EBECEE').';">
                                                            <td style="padding: 0.6em 0.4em;width: 15%;">'.$product['reference'].'</td>
                                                            <td style="padding: 0.6em 0.4em;width: 30%;"><strong>'.$product['name'].(isset($product['attributes']) ? ' - '.$product['attributes'] : '').' - '.Tools::displayError('Customized').(!empty($customization_text) ? ' - '.$customization_text : '').'</strong></td>
                                                            <td style="padding: 0.6em 0.4em; width: 20%;">'.Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ?  Tools::ps_round($price, 2) : $price_wt, $this->context->currency, false).'</td>
                                                            <td style="padding: 0.6em 0.4em; width: 15%;">'.$customization_quantity.'</td>
                                                            <td style="padding: 0.6em 0.4em; width: 20%;">'.Tools::displayPrice($customization_quantity * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt), $this->context->currency, false).'</td>
                                                    </tr>';
                                            }

                                            if (!$customization_quantity || (int)$product['cart_quantity'] > $customization_quantity)
                                                    $products_list .=
                                                    '<tr style="background-color: '.($key % 2 ? '#DDE2E6' : '#EBECEE').';">
                                                            <td style="padding: 0.6em 0.4em;width: 15%;">'.$product['reference'].'</td>
                                                            <td style="padding: 0.6em 0.4em;width: 30%;"><strong>'.$product['name'].(isset($product['attributes']) ? ' - '.$product['attributes'] : '').'</strong></td>
                                                            <td style="padding: 0.6em 0.4em; width: 20%;">'.Tools::displayPrice(Product::getTaxCalculationMethod((int)$this->context->customer->id) == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt, $this->context->currency, false).'</td>
                                                            <td style="padding: 0.6em 0.4em; width: 15%;">'.((int)$product['cart_quantity'] - $customization_quantity).'</td>
                                                            <td style="padding: 0.6em 0.4em; width: 20%;">'.Tools::displayPrice(((int)$product['cart_quantity'] - $customization_quantity) * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt), $this->context->currency, false).'</td>
                                                    </tr>';

                                            // Check if is not a virutal product for the displaying of shipping
                                            if (!$product['is_virtual'])
                                                    $virtual_product &= false;

                                    } // end foreach ($products)

                                    $cart_rules_list = '';
                                    $total_reduction_value_ti = 0;
                                    $total_reduction_value_tex = 0;
                                    foreach ($cart_rules as $cart_rule)
                                    {
                                            $package = array('id_carrier' => $order->id_carrier, 'id_address' => $order->id_address_delivery, 'products' => $order->product_list);
                                            $values = array(
                                                    'tax_incl' => $cart_rule['obj']->getContextualValue(true, $this->context, CartRule::FILTER_ACTION_ALL_NOCAP, $package),
                                                    'tax_excl' => $cart_rule['obj']->getContextualValue(false, $this->context, CartRule::FILTER_ACTION_ALL_NOCAP, $package)
                                            );

                                            // If the reduction is not applicable to this order, then continue with the next one
                                            if (!$values['tax_excl'])
                                                    continue;

                                            /* IF
                                            ** - This is not multi-shipping
                                            ** - The value of the voucher is greater than the total of the order
                                            ** - Partial use is allowed
                                            ** - This is an "amount" reduction, not a reduction in % or a gift
                                            ** THEN
                                            ** The voucher is cloned with a new value corresponding to the remainder
                                            */

                                            if (count($order_list) == 1 && $values['tax_incl'] > ($order->total_products_wt - $total_reduction_value_ti) && $cart_rule['obj']->partial_use == 1 && $cart_rule['obj']->reduction_amount > 0)
                                            {
                                                    // Create a new voucher from the original
                                                    $voucher = new CartRule($cart_rule['obj']->id); // We need to instantiate the CartRule without lang parameter to allow saving it
                                                    unset($voucher->id);

                                                    // Set a new voucher code
                                                    $voucher->code = empty($voucher->code) ? substr(md5($order->id.'-'.$order->id_customer.'-'.$cart_rule['obj']->id), 0, 16) : $voucher->code.'-2';
                                                    if (preg_match('/\-([0-9]{1,2})\-([0-9]{1,2})$/', $voucher->code, $matches) && $matches[1] == $matches[2])
                                                            $voucher->code = preg_replace('/'.$matches[0].'$/', '-'.(intval($matches[1]) + 1), $voucher->code);

                                                    // Set the new voucher value
                                                    if ($voucher->reduction_tax)
                                                    {
                                                            $voucher->reduction_amount = $values['tax_incl'] - ($order->total_products_wt - $total_reduction_value_ti);

                                                            // Add total shipping amout only if reduction amount > total shipping
                                                            if ($voucher->free_shipping == 1 && $voucher->reduction_amount >= $order->total_shipping_tax_incl)
                                                                    $voucher->reduction_amount -= $order->total_shipping_tax_incl;
                                                    }
                                                    else
                                                    {
                                                            $voucher->reduction_amount = $values['tax_excl'] - ($order->total_products - $total_reduction_value_tex);

                                                            // Add total shipping amout only if reduction amount > total shipping
                                                            if ($voucher->free_shipping == 1 && $voucher->reduction_amount >= $order->total_shipping_tax_excl)
                                                                    $voucher->reduction_amount -= $order->total_shipping_tax_excl;
                                                    }

                                                    $voucher->id_customer = $order->id_customer;
                                                    $voucher->quantity = 1;
                                                    $voucher->quantity_per_user = 1;
                                                    $voucher->free_shipping = 0;
                                                    if ($voucher->add())
                                                    {
                                                            // If the voucher has conditions, they are now copied to the new voucher
                                                            CartRule::copyConditions($cart_rule['obj']->id, $voucher->id);

                                                            $params = array(
                                                                    '{voucher_amount}' => Tools::displayPrice($voucher->reduction_amount, $this->context->currency, false),
                                                                    '{voucher_num}' => $voucher->code,
                                                                    '{firstname}' => $this->context->customer->firstname,
                                                                    '{lastname}' => $this->context->customer->lastname,
                                                                    '{id_order}' => $order->reference,
                                                                    '{order_name}' => $order->getUniqReference()
                                                            );
                                                            Mail::Send(
                                                                    (int)$order->id_lang,
                                                                    'voucher',
                                                                    sprintf(Mail::l('New voucher regarding your order %s', (int)$order->id_lang), $order->reference),
                                                                    $params,
                                                                    $this->context->customer->email,
                                                                    $this->context->customer->firstname.' '.$this->context->customer->lastname,
                                                                    null, null, null, null, _PS_MAIL_DIR_, false, (int)$order->id_shop
                                                            );
                                                    }

                                                    $values['tax_incl'] -= $values['tax_incl'] - $order->total_products_wt;
                                                    $values['tax_excl'] -= $values['tax_excl'] - $order->total_products;

                                            }
                                            $total_reduction_value_ti += $values['tax_incl'];
                                            $total_reduction_value_tex += $values['tax_excl'];	

                                            $order->addCartRule($cart_rule['obj']->id, $cart_rule['obj']->name, $values, 0, $cart_rule['obj']->free_shipping);

                                            if ($id_order_state != Configuration::get('PS_OS_ERROR') && $id_order_state != Configuration::get('PS_OS_CANCELED') && !in_array($cart_rule['obj']->id, $cart_rule_used))
                                            {
                                                    $cart_rule_used[] = $cart_rule['obj']->id;

                                                    // Create a new instance of Cart Rule without id_lang, in order to update its quantity
                                                    $cart_rule_to_update = new CartRule($cart_rule['obj']->id);
                                                    $cart_rule_to_update->quantity = max(0, $cart_rule_to_update->quantity - 1);
                                                    $cart_rule_to_update->update();
                                            }

                                            $cart_rules_list .= '
                                            <tr>
                                                    <td colspan="4" style="padding:0.6em 0.4em;text-align:right">'.Tools::displayError('Voucher name:').' '.$cart_rule['obj']->name.'</td>
                                                    <td style="padding:0.6em 0.4em;text-align:right">'.($values['tax_incl'] != 0.00 ? '-' : '').Tools::displayPrice($values['tax_incl'], $this->context->currency, false).'</td>
                                            </tr>';
                                    }

                                    // Specify order id for message
                                    $old_message = Message::getMessageByCartId((int)$this->context->cart->id);
                                    if ($old_message)
                                    {
                                            $update_message = new Message((int)$old_message['id_message']);
                                            $update_message->id_order = (int)$order->id;
                                            $update_message->update();

                                            // Add this message in the customer thread
                                            $customer_thread = new CustomerThread();
                                            $customer_thread->id_contact = 0;
                                            $customer_thread->id_customer = (int)$order->id_customer;
                                            $customer_thread->id_shop = (int)$this->context->shop->id;
                                            $customer_thread->id_order = (int)$order->id;
                                            $customer_thread->id_lang = (int)$this->context->language->id;
                                            $customer_thread->email = $this->context->customer->email;
                                            $customer_thread->status = 'open';
                                            $customer_thread->token = Tools::passwdGen(12);
                                            $customer_thread->add();

                                            $customer_message = new CustomerMessage();
                                            $customer_message->id_customer_thread = $customer_thread->id;
                                            $customer_message->id_employee = 0;
                                            $customer_message->message = $update_message->message;
                                            $customer_message->private = 0;

                                            if (!$customer_message->add())
                                                    $this->errors[] = Tools::displayError('An error occurred while saving message');
                                    }

                                    // Hook validate order
                                    Hook::exec('actionValidateOrder', array(
                                            'cart' => $this->context->cart,
                                            'order' => $order,
                                            'customer' => $this->context->customer,
                                            'currency' => $this->context->currency,
                                            'orderStatus' => $order_status
                                    ));

                                    foreach ($this->context->cart->getProducts() as $product)
                                            if ($order_status->logable)
                                                    ProductSale::addProductSale((int)$product['id_product'], (int)$product['cart_quantity']);

                                    // Set the order state
                                    $new_history = new OrderHistory();
                                    $new_history->id_order = (int)$order->id;
                                    $new_history->changeIdOrderState((int)$id_order_state, $order, true);
                                    $new_history->addWithemail(true, $extra_vars);

                                    // Switch to back order if needed
                                    if (Configuration::get('PS_STOCK_MANAGEMENT') && $order_detail->getStockState())
                                    {
                                            $history = new OrderHistory();
                                            $history->id_order = (int)$order->id;
                                            $history->changeIdOrderState(Configuration::get('PS_OS_OUTOFSTOCK'), $order, true);
                                            $history->addWithemail();
                                    }

                                    unset($order_detail);

                                    // Order is reloaded because the status just changed
                                    $order = new Order($order->id);

                                    // Send an e-mail to customer (one order = one email)
                                    if (    $id_order_state != Configuration::get('PS_OS_ERROR') 
                                            && $id_order_state != Configuration::get('PS_OS_CANCELED') 
                                            && $this->context->customer->id
                                            && $id_order_state != Configuration::get('PBX_PENDING_STATUS')) // seb
                                    {
                                            $invoice = new Address($order->id_address_invoice);
                                            $delivery = new Address($order->id_address_delivery);
                                            $delivery_state = $delivery->id_state ? new State($delivery->id_state) : false;
                                            $invoice_state = $invoice->id_state ? new State($invoice->id_state) : false;

                                            $data = array(
                                            '{firstname}' => $this->context->customer->firstname,
                                            '{lastname}' => $this->context->customer->lastname,
                                            '{email}' => $this->context->customer->email,
                                            '{delivery_block_txt}' => $this->_getFormatedAddress($delivery, "\n"),
                                            '{invoice_block_txt}' => $this->_getFormatedAddress($invoice, "\n"),
                                            '{delivery_block_html}' => $this->_getFormatedAddress($delivery, '<br />', array(
                                                    'firstname'	=> '<span style="font-weight:bold;">%s</span>',
                                                    'lastname'	=> '<span style="font-weight:bold;">%s</span>'
                                            )),
                                            '{invoice_block_html}' => $this->_getFormatedAddress($invoice, '<br />', array(
                                                            'firstname'	=> '<span style="font-weight:bold;">%s</span>',
                                                            'lastname'	=> '<span style="font-weight:bold;">%s</span>'
                                            )),
                                            '{delivery_company}' => $delivery->company,
                                            '{delivery_firstname}' => $delivery->firstname,
                                            '{delivery_lastname}' => $delivery->lastname,
                                            '{delivery_address1}' => $delivery->address1,
                                            '{delivery_address2}' => $delivery->address2,
                                            '{delivery_city}' => $delivery->city,
                                            '{delivery_postal_code}' => $delivery->postcode,
                                            '{delivery_country}' => $delivery->country,
                                            '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
                                            '{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
                                            '{delivery_other}' => $delivery->other,
                                            '{invoice_company}' => $invoice->company,
                                            '{invoice_vat_number}' => $invoice->vat_number,
                                            '{invoice_firstname}' => $invoice->firstname,
                                            '{invoice_lastname}' => $invoice->lastname,
                                            '{invoice_address2}' => $invoice->address2,
                                            '{invoice_address1}' => $invoice->address1,
                                            '{invoice_city}' => $invoice->city,
                                            '{invoice_postal_code}' => $invoice->postcode,
                                            '{invoice_country}' => $invoice->country,
                                            '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
                                            '{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
                                            '{invoice_other}' => $invoice->other,
                                            '{order_name}' => $order->getUniqReference(),
                                            '{date}' => Tools::displayDate(date('Y-m-d H:i:s'),null , 1),
                                            '{carrier}' => $virtual_product ? Tools::displayError('No carrier') : $carrier->name,
                                            '{payment}' => Tools::substr($order->payment, 0, 32),
                                            '{products}' => $this->formatProductAndVoucherForEmail($products_list),
                                            '{discounts}' => $this->formatProductAndVoucherForEmail($cart_rules_list),
                                            '{total_paid}' => Tools::displayPrice($order->total_paid, $this->context->currency, false),
                                            '{total_products}' => Tools::displayPrice($order->total_paid - $order->total_shipping - $order->total_wrapping + $order->total_discounts, $this->context->currency, false),
                                            '{total_discounts}' => Tools::displayPrice($order->total_discounts, $this->context->currency, false),
                                            '{total_shipping}' => Tools::displayPrice($order->total_shipping, $this->context->currency, false),
                                            '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $this->context->currency, false),
                                            '{total_tax_paid}' => Tools::displayPrice(($order->total_products_wt - $order->total_products) + ($order->total_shipping_tax_incl - $order->total_shipping_tax_excl), $this->context->currency, false));

                                            if (is_array($extra_vars))
                                                    $data = array_merge($data, $extra_vars);

                                            // Join PDF invoice
                                            if ((int)Configuration::get('PS_INVOICE') && $order_status->invoice && $order->invoice_number)
                                            {
                                                    $pdf = new PDF($order->getInvoicesCollection(), PDF::TEMPLATE_INVOICE, $this->context->smarty);
                                                    $file_attachement['content'] = $pdf->render(false);
                                                    $file_attachement['name'] = Configuration::get('PS_INVOICE_PREFIX', (int)$order->id_lang, null, $order->id_shop).sprintf('%06d', $order->invoice_number).'.pdf';
                                                    $file_attachement['mime'] = 'application/pdf';
                                            }
                                            else
                                                    $file_attachement = null;

                                            if (Validate::isEmail($this->context->customer->email))
                                                    Mail::Send(
                                                            (int)$order->id_lang,
                                                            'order_conf',
                                                            Mail::l('Order confirmation', (int)$order->id_lang),
                                                            $data,
                                                            $this->context->customer->email,
                                                            $this->context->customer->firstname.' '.$this->context->customer->lastname,
                                                            null,
                                                            null,
                                                            $file_attachement,
                                                            null, _PS_MAIL_DIR_, false, (int)$order->id_shop
                                                    );
                                    }

                                    // updates stock in shops
                                    if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT'))
                                    {
                                            $product_list = $order->getProducts();
                                            foreach ($product_list as $product)
                                            {
                                                    // if the available quantities depends on the physical stock
                                                    if (StockAvailable::dependsOnStock($product['product_id']))
                                                    {
                                                            // synchronizes
                                                            StockAvailable::synchronize($product['product_id'], $order->id_shop);
                                                    }
                                            }
                                    }
                            }
                            else
                            {
                                    $error = Tools::displayError('Order creation failed');
                                    Logger::addLog($error, 4, '0000002', 'Cart', intval($order->id_cart));
                                    die($error);
                            }
                    } // End foreach $order_detail_list
                    // Use the last order as currentOrder
                    $this->currentOrder = (int)$order->id;
                    return true;
            }
            else
            {
                    $error = Tools::displayError('Cart cannot be loaded or an order has already been placed using this cart');
                    Logger::addLog($error, 4, '0000001', 'Cart', intval($this->context->cart->id));
                    die($error);
            }
    }

}
