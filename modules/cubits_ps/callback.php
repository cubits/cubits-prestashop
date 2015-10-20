<?php
  include(dirname(__FILE__).'/../../config/config.inc.php');
  include(dirname(__FILE__).'/lib/cubits-php/lib/Cubits.php');
  include(dirname(__FILE__).'/cubits_ps.php');

  $cubits_pay = new Cubits_ps();
  $params = json_decode(file_get_contents('php://input'));
  $payment_id = $params->id;
  $order_id_ref = (int)$params->reference;
  Cubits::configure("https://pay.cubits.com/api/v1/",true);
  $cubits = Cubits::withApiKey(Configuration::get($cubits_pay->name.'_cubits_key'), Configuration::get($cubits_pay->name.'_cubits_secret'));
  $invoice_data = $cubits->getInvoice($payment_id);

  $order_id = (int)$invoice_data->reference;
  $cubits_pay->parse_callback($order_id, $order_id_ref, $invoice_data->status);
  exit;
?>
