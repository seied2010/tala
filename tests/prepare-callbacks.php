<?php
wp_set_current_user(1);
if(GoldCommerce\Plugin::mode()!=='standalone')throw new Exception('Test authority must be standalone');
$p=wc_get_product((int)get_option('gc_load_product'));$fixtures=[];
for($i=0;$i<1000;$i++){
    $o=wc_create_order(['status'=>'gc-await-quote']);$o->set_currency('IRR');$o->set_billing_email('callback-fixture@example.test');$o->add_product($p,1);$o->update_meta_data('_gc_specialized','yes');$o->save();
    $q=GoldCommerce\Quotes::issue($o->get_id());$b=GoldCommerce\Quotes::begin($q['uuid'],$q['token']);$fixtures[]=['order_id'=>$o->get_id(),'intent'=>$b['intent'],'amount_irr'=>$q['amount_irr']];
    if(($i+1)%100===0)echo 'Prepared '.($i+1)."\n";
}
update_option('gc_prepared_callbacks',$fixtures,false);echo "Prepared callbacks ready; no secret values printed.\n";
