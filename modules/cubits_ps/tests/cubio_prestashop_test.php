<?php
require_once 'config.php';
include(dirname(__FILE__).'/../../../config/config.inc.php');
include(dirname(__FILE__).'/../cubits_prestashop.php');
class CubitsPrestashopTest extends PHPUnit_Extensions_Selenium2TestCase {

    protected function setUp(){
        $this->setBrowser('firefox');
        $this->setBrowserUrl('');
    }

    public function testInstallConfigUninstall()
    {
        $this->login_admin();
        $this->byId('maintab-AdminParentModules')->click();
        $this->byCssSelector('li[id="subtab-AdminPayment"] > a')->click();
        sleep(1);
        $this->byCssSelector('div[class="panel-footer"] > div > a')->click();
        sleep(1);
        $this->byCssSelector('a[data-module-name="Cubits\ Pay"]')->click();
        sleep(1);
        $this->byId('proceed-install-anyway')->click();
        sleep(1);
        $this->byId('maintab-AdminParentModules')->click();
        $this->byCssSelector('li[id="subtab-AdminPayment"] > a')->click();
        sleep(1);
        $this->byCssSelector('div[class="panel-footer"] > div > a')->click();
        sleep(1);
        $this->byCssSelector('a[href*="module_name=cubits_ps"]')->click();
        sleep(1);
        $this->byCssSelector('input[name="cubits_key"]')->value('example_cubits_key123456');
        sleep(1);
        $this->byCssSelector('input[name="cubits_secret"]')->value('very secret');
        sleep(1);
        $this->byCssSelector('input[name="submit"]')->click();
        $key_input = $this->byName('cubits_key');
        $this->assertEquals($key_input->attribute('value'), 'example_cubits_key123456');
    }

    public function testCreateOrder(){
        $this->url('http://localhost/prestashop/index.php?id_product=2&controller=product&id_lang=1');
        sleep(1);
        $this->byCssSelector('p[id="add_to_cart"] > button')->click();
        sleep(1);
        $this->byCssSelector('a[href="http://localhost/prestashop/index.php?controller=order"]')->click();
        sleep(1);
        $this->url('http://localhost/prestashop/index.php?controller=order&step=1');
        $mail = $this->byId('email');
        $mail->value( CUSTOMER_MAIL );
        $pw = $this->byID('passwd');
        $pw->value( CUSTOMER_PASSWORD );
        $subbmit = $this->byId('SubmitLogin');
        $subbmit->click();
        $this->byName('processAddress')->click();
        $this->byId('cgv')->click();
        $this->byName('processCarrier');
        $this->url('http://localhost/prestashop/modules/cubits_ps/controllers/payment.php');
        $ref = $this->byId('order_ref')->attribute('data-value');
        $this->url('http://localhost/prestashop/index.php?controller=history');
        $this->assertContains($ref, $this->byTag('body')->text());

        // test different callbacks with the order
        $sql = 'SELECT * FROM '._DB_PREFIX_.'orders WHERE reference = "'.$ref.'"';
        $order_arr = Db::getInstance()->getRow($sql);
        // need to update config
        Configuration::updateValue('PS_OS_CUBITSSTATE', $order_arr['current_state']);
        $cubits_pay = new Cubits_prestashop();

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'unconfirmed');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_CUBITSSTATE'));

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'anything_else');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_CUBITSSTATE'));

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'completed');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_PAYMENT'));

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'timeout');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_CANCELED'));

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'overpaid');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_PAYMENT'));

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'underpaid');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_CUBITSSTATE'));

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'aborted');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_CANCELED'));

        $cubits_pay->parse_callback($order_arr['id_order'], $order_arr['id_order'], 'pending');
        $order_arr = Db::getInstance()->getRow($sql);
        $this->assertEquals($order_arr['current_state'], Configuration::get('PS_OS_CUBITSSTATE'));


    }

    public function testUninstall(){
        $this->login_admin();
        $this->url('http://localhost/prestashop/'.ADMIN_PATH);
        $this->byId('maintab-AdminParentModules')->click();
        sleep(1);
        $this->byCssSelector('li[id="subtab-AdminPayment"] > a')->click();
        sleep(1);
        $this->byCssSelector('div[class="panel-footer"] > div > a')->click();
        sleep(1);
        $this->byCssSelector('a[href*="module_name=cubits_ps"] + button')->click();
        $this->byCssSelector('a[href*="uninstall=cubits_ps"]')->click();
        $this->acceptAlert();
    }

    public function login_admin(){
        $this->url('http://localhost/prestashop/'.ADMIN_PATH);
        $mail = $this->byId('email');
        $mail->value( ADMIN_MAIL );
        $pw = $this->byId('passwd');
        $pw->value( ADMIN_PASSWORD );
        $subbmit = $this->byClassName('ladda-button');
        $subbmit->click();
    }
}
