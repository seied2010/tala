<?php
declare(strict_types=1);
namespace GoldCommerce;
/** Deliberately unavailable on production environments; no real funds are moved. */
final class TestGateway extends \WC_Payment_Gateway {
    public function __construct(){
        $this->id='gc_test';$this->method_title='Gold TEST ONLY';$this->title='پرداخت آزمایشی — بدون انتقال وجه';$this->enabled='yes';$this->supports=['products','refunds'];
    }
    public function is_available(){return wp_get_environment_type()!=='production'&&!empty(Plugin::settings()['test_mode'])&&parent::is_available();}
    public function process_payment($order_id){
        if(!$this->is_available())throw new \Exception('test_gateway_disabled');
        $order=wc_get_order($order_id);$q=Quotes::latest((int)$order_id);
        if(!$q)throw new \Exception('quote_required');
        Quotes::settle((int)$order_id,'TEST-'.$q['intent'],$q['amount_irr'],(string)$q['intent']);
        WC()->cart?->empty_cart();return ['result'=>'success','redirect'=>$this->get_return_url($order)];
    }
    public function process_refund($order_id,$amount=null,$reason=''){return wp_get_environment_type()!=='production';}
}
