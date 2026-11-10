<?php
/** Test harness only. Must never be installed on a real store. */
if(!defined('ABSPATH')||wp_get_environment_type()!=='local'||!defined('GC_TEST_HTTP_SECRET'))return;
add_action('rest_api_init',static function(){
    register_rest_route('gold-test/v1','/run/(?P<operation>browse|product|search|request|issue|checkout|callback|queue)',['methods'=>'POST','permission_callback'=>static fn($r)=>hash_equals(GC_TEST_HTTP_SECRET,(string)$r->get_header('X-GC-Test')),'callback'=>static function($r){
        wp_set_current_user(1);$operation=$r['operation'];
        $p=wc_get_product((int)get_option('gc_load_product'));if(!$p)return new WP_Error('fixture_missing','Fixture not configured',['status'=>503]);
        try{
            if($operation==='browse')return ['count'=>count(wc_get_products(['status'=>'publish','limit'=>12,'return'=>'ids']))];
            if($operation==='product')return GoldCommerce\Plugin::price($p);
            if($operation==='search')return ['count'=>count(wc_get_products(['status'=>'publish','limit'=>12,'s'=>'آزمایشی','return'=>'ids']))];
            if($operation==='queue'){GoldAgah\Connector::drain();return ['mode'=>'MOCK_ONLY'];}
            if($operation==='callback'&&isset($r['fixture_index'])){
                $fixtures=(array)get_option('gc_prepared_callbacks',[]);$fixture=$fixtures[(int)$r['fixture_index']]??null;
                if(!$fixture)throw new RuntimeException('callback_fixture_missing');
                return ['status'=>GoldCommerce\Quotes::settle((int)$fixture['order_id'],'PREPARED-'.$fixture['intent'],$fixture['amount_irr'],$fixture['intent'])];
            }
            $o=wc_create_order(['status'=>'gc-await-quote']);$o->set_currency('IRR');$o->set_billing_email('load@example.test');$o->add_product($p,1);$o->update_meta_data('_gc_specialized','yes');$o->save();
            if($operation==='request')return ['status'=>'requested','order_id'=>$o->get_id()];
            $q=GoldCommerce\Quotes::issue($o->get_id());
            if($operation==='issue')return ['status'=>'issued'];
            $b=GoldCommerce\Quotes::begin($q['uuid'],$q['token']);
            if($operation==='checkout')return ['status'=>'payment_started'];
            return ['status'=>GoldCommerce\Quotes::settle($o->get_id(),'LOAD-'.$b['intent'],$q['amount_irr'],$b['intent'])];
        }catch(Throwable $e){$code=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'stock_or_database_conflict';file_put_contents('/project/evidence/load-errors.jsonl',wp_json_encode(['at_utc'=>gmdate('c'),'operation'=>$operation,'code'=>$code,'order_id'=>isset($o)?$o->get_id():null])."\n",FILE_APPEND|LOCK_EX);return new WP_Error('load_operation_failed',$code,['status'=>409]);}
    }]);
});
