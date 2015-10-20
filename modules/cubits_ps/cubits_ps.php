<?php
if (!defined('_PS_VERSION_'))
  exit;

class Cubits_ps extends PaymentModule
{
  public function __construct()
  {
    $this->name = 'cubits_ps';
    $this->tab = 'payments_gateways';
    $this->version = '1.0.0';
    $this->author = 'Dooga Ltd.';
    $this->need_instance = 1;
    $this->currencies = true;
    $this->currencies_mode = 'checkbox';
    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('Bitcoin');
    $this->description = $this->l('Cubits Bitcoin payment provider.');

    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    if (!Configuration::get('CUBITS_PS_PLUGIN'))
      $this->warning = $this->l('No name provided');
  }

  public function install()
  {
    if (!parent::install() || !$this->registerHook('invoice') || !$this->registerHook('payment') || !$this->registerHook('paymentReturn')) {
      return false;
    }
    else{
      Configuration::updateValue($this->name.'_cubits_key', '');
      Configuration::updateValue($this->name.'_cubits_secret', '');
      Configuration::updateValue('CUBITS_CANCEL_URL', (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__);

      // create new order status CUBITSSTATE
      $values_to_insert = array(
      'invoice' => 0,
      'send_email' => 0,
      'module_name' => $this->name,
      'color' => 'RoyalBlue',
      'unremovable' => 0,
      'hidden' => 0,
      'logable' => 0,
      'delivery' => 0,
      'shipped' => 0,
      'paid' => 0,
      'deleted' => 0);

      if(!Db::getInstance()->autoExecute(_DB_PREFIX_.'order_state', $values_to_insert, 'INSERT'))
        return false;
      $id_order_state = (int)Db::getInstance()->Insert_ID();
      $languages = Language::getLanguages(false);
      foreach ($languages as $language)
        Db::getInstance()->autoExecute(_DB_PREFIX_.'order_state_lang', array('id_order_state'=>$id_order_state, 'id_lang'=>$language['id_lang'], 'name'=>'Awaiting Cubits payment', 'template'=>''), 'INSERT');
      // if (!@copy(dirname(__FILE__).DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.'logo.gif', _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.'os'.DIRECTORY_SEPARATOR.$id_order_state.'.gif'))
      //   return false;
      Configuration::updateValue('PS_OS_CUBITSSTATE', $id_order_state);
      unset($id_order_state);


    }
  }

  public function uninstall()
  {
    if(parent::uninstall() && Configuration::deleteByName($this->name.'_cubits_key') && Configuration::deleteByName($this->name.'_cubits_secret') && Configuration::deleteByName('CUBITS_CANCEL_URL')){
      Db::getInstance()->delete(_DB_PREFIX_.'order_state', 'module_name = "'.$this->name.'"', 1);
      return true;
    }
    else{ return fasle; }
  }

  private function _displayForm()
  {
    $this->_html = '
      <form action="'.$_SERVER['REQUEST_URI'].'" method="post">
        <label>'.$this->l('Cubits Bitcoin Settings').'</label><hr>
        <div class="margin-form">
          <label>'.$this->l('Cubits Key').'</label>
          <input type="text" name="cubits_key" value="'.Configuration::get($this->name.'_cubits_key').'" />
          <label>'.$this->l('Cubits Secret').'</label>
          <input type="password" name="cubits_secret" value="'.Configuration::get($this->name.'_cubits_secret').'" />
        </div>
        <input type="submit" name="submit" value="'.$this->l('Update').'" class="button" />
      </form>';
  }

  public function getContent()
  {
    if (Tools::isSubmit('submit'))
    {
      Configuration::updateValue($this->name.'_cubits_key', Tools::getValue('cubits_key'));
      Configuration::updateValue($this->name.'_cubits_secret', Tools::getValue('cubits_secret'));
    }
    $this->_displayForm();
    return $this->_html;
  }

  function hookPayment($params)
  {
    global $smarty;

    $smarty->assign(array(
                'this_path' => $this->_path,
                'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

    return $this->display(__FILE__, 'views/templates/payment.tpl');
  }

  public function hookPaymentReturn($params){
    if (!$this->active)
      return ;
  }

  public function execPayment(){
    $cart = Context::getContext()->cart;
    $total = $cart->getOrderTotal(true);
    $currency = Context::getContext()->currency;
    $customer = Context::getContext()->customer;
    $this->validateOrder($cart->id, Configuration::get('PS_OS_CUBITSSTATE'), $total, $this->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);
    $order = new Order($this->currentOrder);
    $ordered_products = $cart->getProducts();
    $names = array();
    $description = array();

    foreach ($ordered_products as $product) {
      $names[] = $product['quantity'].' x '.$product['name'];
    }
    $description = implode('<br/> ', $names);
    $name = 'Order #: '.$order->id;

    if (strlen($description) > 512){
      $description = substr($description, 0, 509).'...';
    }

    Cubits::configure("https://pay.cubits.com/api/v1/",true);

    $cubits = Cubits::withApiKey(Configuration::get($this->name.'_cubits_key'), Configuration::get($this->name.'_cubits_secret'));
    $options = array(
      'success_url' => (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->id.'&id_order='.$this->currentOrder,
      'callback_url' => (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/callback.php",
      'cancel_url' => Configuration::get('CUBITS_CANCEL_URL'),
      'reference' => $order->id,
      'description' => $description
    );
    try{
      $response = $cubits->createInvoice($name, $cart->getOrderTotal(true), $currency->iso_code, $options);
      if ($response->invoice_url){
        Tools::redirect($response->invoice_url);
      }
    }catch(Exception $e){
      echo 'An error has occurred, please try another method. Order reference: <span id="order_ref" data-value="'.$order->reference.'">'.$order->reference.'</order>';
    }

  }

  public function parse_callback($order_id, $order_id_ref, $status){
    if($order_id == $order_id_ref){
      $order = new Order($order_id);
      $history = new OrderHistory();
      $history->id_order = (int)$order->id;
      switch ($status) {
      case 'completed':
      case 'overpaid':
          $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $order);
      break;
      case 'pending':
      case 'underpaid':
      case 'unconfirmed':
        $history->changeIdOrderState((int)Configuration::get('PS_OS_CUBITSSTATE'), $order);
      break;
      case 'aborted':
      case 'timeout':
        $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $order);
      break;
      }
      $history->addWs();
    }
  }

}

?>
