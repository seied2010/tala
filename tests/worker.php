<?php
use GoldCommerce\{Quotes,Plugin};
wp_set_current_user(1);
$op=getenv('GC_WORKER_OP');
try{
    if($op==='setup'){
        $p=new WC_Product_Simple();$p->set_name('Concurrent test fixture');$p->set_status('publish');$p->set_catalog_visibility('hidden');$p->set_regular_price('100');$p->set_virtual(true);$p->set_manage_stock(true);$p->set_stock_quantity(1);$p->set_backorders('no');$p->set_tax_status('none');$p->update_meta_data('_gc_product',['price_type'=>'MANUAL_FINAL','fixed_price'=>'100','net_weight'=>'0','gross_weight'=>'0']);$p->save();$ids=[];
        for($i=0;$i<2;$i++){$o=wc_create_order(['status'=>'gc-await-quote']);$o->set_billing_email('concurrency@example.test');$o->set_currency('IRR');$o->add_product($p,1);$o->update_meta_data('_gc_specialized','yes');$o->save();$ids[]=$o->get_id();}
        echo wp_json_encode(['orders'=>$ids,'product'=>$p->get_id()]);
    }elseif($op==='issue'){
        $q=Quotes::issue((int)getenv('GC_ORDER_ID'));echo wp_json_encode(['ok'=>true,'uuid'=>$q['uuid'],'token'=>$q['token'],'order_id'=>$q['order_id']]);
    }elseif($op==='begin'){
        $b=Quotes::begin((string)getenv('GC_QUOTE_UUID'),(string)getenv('GC_QUOTE_TOKEN'));echo wp_json_encode(['ok'=>true,'intent'=>$b['intent'],'order_id'=>$b['order_id']]);
    }elseif($op==='settle'){
        $status=Quotes::settle((int)getenv('GC_ORDER_ID'),'CONCURRENT-'.getenv('GC_INTENT'),'100',(string)getenv('GC_INTENT'));echo wp_json_encode(['ok'=>true,'status'=>$status]);
    }elseif($op==='check'){
        $o=wc_get_order((int)getenv('GC_ORDER_ID'));$p=wc_get_product((int)getenv('GC_PRODUCT_ID'));echo wp_json_encode(['paid'=>$o->is_paid(),'stock'=>(int)$p->get_stock_quantity(),'quote_status'=>Quotes::latest($o->get_id())['status']]);
    }
}catch(Throwable $e){echo wp_json_encode(['ok'=>false,'code'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'stock_or_resource_unavailable']);}
