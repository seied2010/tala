<?php
/** Run explicitly with wp eval-file. Never runs during activation. */
use GoldCommerce\Database;
wp_set_current_user(1);
Database::install();
GoldCommerce\ClassicCheckout::configure(true);
update_option('WPLANG','fa_IR');update_option('timezone_string','Asia/Tehran');
update_option('woocommerce_currency','IRR');update_option('woocommerce_price_num_decimals',0);update_option('woocommerce_manage_stock','yes');update_option('woocommerce_custom_orders_table_enabled','yes');update_option('woocommerce_custom_orders_table_data_sync_enabled','no');
update_option('woocommerce_enable_guest_checkout','yes');update_option('woocommerce_default_country','IR');update_option('woocommerce_allowed_countries','specific');update_option('woocommerce_specific_allowed_countries',['IR']);update_option('woocommerce_ship_to_countries','');
update_option('woocommerce_coming_soon','no');update_option('woocommerce_store_pages_only','no');
update_option('woocommerce_tax_based_on','shipping');update_option('woocommerce_calc_taxes','no');
$settings=GoldCommerce\Plugin::settings();$settings['finance_configured']=true;$settings['shipping_configured']=true;$settings['guest_quotes']=true;$settings['test_mode']=true;$settings['rate_ttl']=86400;$settings['quote_ttl']=180;$settings['payment_grace']=900;$settings['policy']=['version'=>'FICTIONAL-TEST-ONLY-1','tax_percent'=>'9','tax_components'=>['making','profit'],'rounding'=>'half_up'];update_option('gc_settings',$settings);
update_option('gc_mode','standalone');
$second=DB_NAME==='gold2';update_option('blogname',$second?'زرین — فروشگاه نمونه دوم':'نشان — فروشگاه نمونه اول');update_option('gc_contact_text','فروشگاه ساختگی برای آزمون — اطلاعات تماس واقعی وارد نشده است.');
set_theme_mod('gc_accent',$second?'#426d82':'#a77735');set_theme_mod('gc_background',$second?'#f3f7fa':'#faf8f3');
set_theme_mod('gc_hero_title',$second?'روایت تازه‌ای از زیبایی.':'زیبایی، در جزئیات ماندگار است.');
$data=[['new','انگشتر طلای نو','WEIGHT_BASED','2.5'],['stone','گردنبند سنگ‌دار','WEIGHT_BASED','3.2'],['coin','سکه نمونه','MANUAL_FINAL','0'],['bullion','شمش نمونه','MANUAL_FINAL','0'],['used','دستبند دست دوم','FIXED','4'],['custom','ساخت سفارشی','REQUEST_QUOTE','2']];$ids=[];
foreach($data as $i=>[$kind,$name,$type,$weight]){
    $sku='SAMPLE-'.strtoupper($kind);$existing=wc_get_product_id_by_sku($sku);$p=$existing?wc_get_product($existing):new WC_Product_Simple();
    $p->set_name($name.' — داده آزمایشی');$p->set_slug(sanitize_title($name));$p->set_status('publish');$p->set_sku($sku);$p->set_regular_price('10000000');$p->set_manage_stock(true);$p->set_stock_quantity(30);$p->set_backorders('no');$p->set_virtual(false);$p->set_tax_status('none');$p->set_description('این کالا و قیمت‌ها صرفاً داده ساختگی آزمون هستند و برای فروش واقعی نیستند. وزن، اجرت و نرخ در پنل مدیریت قابل تنظیم‌اند.');$p->set_short_description('طراحی نمونه · جزئیات قیمت روشن · امکان استعلام');
    $p->update_meta_data('_gc_product',['category'=>$kind,'price_type'=>$type,'gross_weight'=>$weight,'net_weight'=>$weight,'stone_weight'=>'0','stone_value'=>$kind==='stone'?'4000000':'0','purity'=>'750','fixed_price'=>'25000000','making_mode'=>'fixed','making_value'=>'1000000','profit_mode'=>'percent','profit_value'=>'7','services'=>'0','supply_status'=>'available','rate_source'=>'manual']);
    $term=term_exists($name,'product_cat');if(!$term)$term=wp_insert_term($name,'product_cat',['description'=>'مجموعه نمونه برای آزمون نصب مستقل']);if(!is_wp_error($term))$p->set_category_ids([(int)$term['term_id']]);$p->save();$ids[$kind]=$p->get_id();
}
GoldCommerce\Rates::put(['value'=>'10000000','currency'=>'IRR','unit'=>'IRR_PER_GRAM','reference_purity'=>'750','valid_until'=>time()+86400]);
$zone=new WC_Shipping_Zone(0);$methods=$zone->get_shipping_methods();$has=false;foreach($methods as $method)if($method->id==='flat_rate')$has=true;if(!$has){$id=$zone->add_shipping_method('flat_rate');update_option('woocommerce_flat_rate_'.$id.'_settings',['title'=>'ارسال امن — نمونه آزمایشی','tax_status'=>'none','cost'=>'100000','type'=>'class']);}
$posts=['راهنمای انتخاب عیار'=>'عیار، وزن خالص و وزن سنگ در قیمت طلا نقش متفاوت دارند. پیش از خرید جزئیات فاکتور را بررسی کنید.','خواندن فاکتور طلا'=>'اصل طلا، اجرت، سود، مالیات و حمل باید به صورت روشن در فاکتور دیده شوند.','مراقبت از زیورآلات'=>'برای نگهداری زیورآلات به راهنمای سازنده و شرایط فروشگاه توجه کنید.'];foreach($posts as $title=>$body)if(!get_page_by_path(sanitize_title($title),OBJECT,'post'))wp_insert_post(['post_title'=>$title,'post_name'=>sanitize_title($title),'post_content'=>$body,'post_status'=>'publish','post_type'=>'post']);
update_option('gc_sample_product_ids',$ids);echo wp_json_encode(['database'=>DB_NAME,'store_uuid'=>get_option('gc_store_uuid'),'products'=>$ids,'brand'=>get_bloginfo('name')],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
