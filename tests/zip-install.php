<?php
use GoldCommerce\{Database,Quotes,Plugin};
wp_set_current_user(1);$out=[];
$assert=static function($v){if(!$v)throw new RuntimeException('Assertion failed');};
$assert(!is_link(WP_PLUGIN_DIR.'/gold-commerce-core'));
$assert(class_exists('GoldCommerce\\Plugin'));
$pages=get_option('gc_pages');$assert(count($pages)>=13);$old=(int)wp_count_posts('page')->publish;Database::install();$assert($old===(int)wp_count_posts('page')->publish);$assert($pages===get_option('gc_pages'));
$p=wc_get_product(wc_get_product_id_by_sku('SAMPLE-coin'));$p->set_virtual(true);$p->save();$o=wc_create_order(['status'=>'gc-await-quote']);$o->add_product($p,1);$o->set_currency('IRR');$o->set_billing_email('zip@example.test');$o->save();$q=Quotes::issue($o->get_id());$b=Quotes::begin($q['uuid'],$q['token']);$assert(Quotes::settle($o->get_id(),'ZIP-'.$b['intent'],$q['amount_irr'],$b['intent'])==='paid');$assert(wc_get_order($o->get_id())->is_paid());
echo wp_json_encode(['test'=>'T33_T34.clean_zip_install','status'=>'PASS','at_utc'=>gmdate('c'),'core_version'=>GC_VERSION,'php'=>PHP_VERSION,'wordpress'=>get_bloginfo('version'),'woocommerce'=>WC_VERSION,'hpos'=>get_option('woocommerce_custom_orders_table_enabled'),'pages'=>count($pages),'repeat_install'=>'no duplicate pages and no SQL error','payment'=>'TEST_ONLY verified paid; no real funds','core_plugin_path'=>realpath(WP_PLUGIN_DIR.'/gold-commerce-core')],JSON_PRETTY_PRINT).PHP_EOL;
