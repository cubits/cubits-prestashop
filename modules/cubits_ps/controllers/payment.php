<?php
include(dirname(__FILE__).'/../../../config/config.inc.php');
include(dirname(__FILE__).'/../../../header.php');
include(dirname(__FILE__).'/../cubits_ps.php');
include(dirname(__FILE__).'/../lib/cubits-php/lib/Cubits.php');

if (!$cookie->isLogged())
    Tools::redirect('authentication.php?back=order.php');

$cubits_presta = new Cubits_ps();

$cubits_presta->execPayment();
include(dirname(__FILE__).'/../../../footer.php');

?>