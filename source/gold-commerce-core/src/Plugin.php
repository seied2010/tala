<?php
declare(strict_types=1);
namespace GoldCommerce;

final class Plugin {
    public static function settings():array{return (array)get_option('gc_settings',[]);}
    public static function mode():string{return (string)apply_filters('gc_authority_mode',get_option('gc_mode','standalone'));}
    public static function boot():void{
        Catalog::boot();
        Privacy::boot();
        Cache::boot();
        add_filter('gettext',[Translations::class,'translate'],20,3);
        add_filter('rest_pre_dispatch',[ClassicCheckout::class,'guard'],10,3);
        add_filter('cron_schedules',static function($s){$s['gc_minute']=['interval'=>60,'display'=>'Gold Commerce every minute'];return $s;});
        add_action('init',static function(){if(get_option('gc_schema')!=='4')Database::install();},5);
        add_action('init',[self::class,'statuses']);
        add_filter('wc_order_statuses',static function($s){return $s+['wc-gc-await-quote'=>'در انتظار مظنه','wc-gc-quote-sent'=>'مظنه ارسال‌شده','wc-gc-preparing'=>'در حال آماده‌سازی','wc-gc-ready'=>'آماده ارسال','wc-gc-shipped'=>'ارسال‌شده','wc-gc-delivered'=>'تحویل‌شده'];});
        add_action('init',static function(){if(function_exists('as_has_scheduled_action')&&!as_has_scheduled_action('gc_maintenance',[],'gold-commerce'))as_schedule_recurring_action(time()+60,60,'gc_maintenance',[],'gold-commerce',true);},30);
        add_action('gc_maintenance',static function(){update_option('gc_last_maintenance',time(),false);Quotes::expire();Notifications::drain();Rates::refresh();Privacy::cleanup();do_action('gc_queue_tick');});
        add_action('gc_send_notifications',[Notifications::class,'drain']);
        add_action('woocommerce_product_options_general_product_data',[Admin::class,'productFields']);
        add_action('woocommerce_admin_process_product_object',[Admin::class,'saveProduct']);
        add_filter('woocommerce_is_purchasable',[self::class,'purchasable'],20,2);
        add_filter('woocommerce_variation_is_purchasable',[self::class,'purchasable'],20,2);
        add_filter('woocommerce_get_price_html',[self::class,'priceHtml'],20,2);
        add_action('woocommerce_single_product_summary',[Customer::class,'productRequest'],31);
        add_filter('woocommerce_add_cart_item_data',[self::class,'cartData'],20,3);
        add_action('woocommerce_before_calculate_totals',[self::class,'cartPrices'],20);
        add_action('woocommerce_check_cart_items',[self::class,'checkCart']);
        add_action('woocommerce_after_cart_totals',[Customer::class,'acceptPriceForm']);
        add_action('woocommerce_checkout_create_order_line_item',[self::class,'orderItem'],20,4);
        add_action('woocommerce_checkout_order_processed',[self::class,'checkout'],5,3);
        add_filter('woocommerce_coupon_is_valid',static function($valid){if(WC()->cart)foreach(WC()->cart->get_cart() as $row)if($row['data']->get_meta('_gc_product'))throw new \Exception('تخفیف طلا باید در اجزای قیمت و مظنه ثبت شود.');return $valid;});
        add_filter('woocommerce_valid_order_statuses_for_payment',[self::class,'paymentStatuses'],20,2);
        add_action('woocommerce_before_pay_action',[self::class,'guardPay'],5);
        add_action('woocommerce_pre_payment_complete',[self::class,'beforePaid'],5,2);
        add_action('woocommerce_payment_complete',[self::class,'genericPaid'],5);
        add_action('woocommerce_order_status_failed',static function($id){Quotes::fail((int)$id);});
        add_action('woocommerce_order_status_cancelled',static function($id){Quotes::fail((int)$id);Database::audit('order.cancelled',(string)$id);});
        add_action('woocommerce_order_refunded',static function($id,$refundId){Database::audit('order.refunded',(string)$id,['refund_id'=>$refundId]);do_action('gc_refund_event',$id,$refundId);},20,2);
        add_action('template_redirect',[Customer::class,'route'],1);
        add_action('rest_api_init',[Rest::class,'register']);
        add_action('admin_menu',[Admin::class,'menu']);
        add_action('admin_post_gc_action',[Admin::class,'action']);
        add_action('admin_post_gc_export',[Reports::class,'download']);
        add_shortcode('gc_tracking',[Customer::class,'tracking']);
        add_shortcode('gc_contact',static function(){return '<p>'.esc_html(get_option('gc_contact_text','اطلاعات تماس فروشگاه در تنظیمات تکمیل می‌شود.')).'</p>';});
        add_action('woocommerce_account_dashboard',[Customer::class,'dashboard']);
        add_action('woocommerce_order_details_after_order_table',[Customer::class,'orderDetails']);
        add_action('wp_enqueue_scripts',static function(){wp_enqueue_script('gc-customer',plugins_url('assets/customer.js',GC_FILE),[],GC_VERSION,true);wp_enqueue_style('gc-customer',plugins_url('assets/customer.css',GC_FILE),[],GC_VERSION);});
        add_action('send_headers',[self::class,'privateHeaders']);
        add_action('template_redirect',[self::class,'privateHeaders'],0);
        add_filter('wp_robots',[self::class,'robots']);
        add_filter('wpseo_robots_array',static function($r){if(self::isPrivate()||is_tag()||is_tax('product_tag')){$r['index']='noindex';$r['follow']='follow';}return $r;});
        add_filter('wpseo_sitemap_exclude_taxonomy',static fn($excluded,$tax)=>$excluded||in_array($tax,['post_tag','product_tag'],true),10,2);
        add_filter('woocommerce_structured_data_product',[self::class,'schema'],20,2);
        add_filter('woocommerce_currency_symbol',static fn($symbol,$currency)=>$currency==='IRR'?'ریال':$symbol,10,2);
        add_filter('wc_get_price_decimals',static fn($n)=>get_woocommerce_currency()==='IRR'?0:$n);
        add_filter('woocommerce_available_payment_gateways',static function($g){if(is_checkout()&&!empty(self::settings()['test_mode'])&&wp_get_environment_type()==='production')unset($g['gc_test']);return $g;});
        add_filter('woocommerce_payment_gateways',static function($g){if(!empty(self::settings()['test_mode'])&&wp_get_environment_type()!=='production'){require_once __DIR__.'/TestGateway.php';$g[]=TestGateway::class;}return $g;});
        add_action('admin_notices',static function(){if(current_user_can('gc_manage_settings')&&empty(self::settings()['finance_configured']))echo '<div class="notice notice-warning"><p>Gold Commerce: قواعد مالی و روش ارسال را در تنظیمات تأیید کنید. فروش مستقیم تا تکمیل تنظیمات بسته است.</p></div>';});
    }
    public static function statuses():void{foreach(['gc-await-quote','gc-quote-sent','gc-preparing','gc-ready','gc-shipped','gc-delivered'] as $s)register_post_status('wc-'.$s,['label'=>$s,'public'=>false,'show_in_admin_all_list'=>true,'show_in_admin_status_list'=>true,'label_count'=>_n_noop($s.' <span class="count">(%s)</span>',$s.' <span class="count">(%s)</span>')]);}
    public static function model(\WC_Product $product):array{
        $model=(array)$product->get_meta('_gc_product');
        if(!$model&&$product->get_parent_id())$model=(array)wc_get_product($product->get_parent_id())?->get_meta('_gc_product');
        return $model;
    }
    public static function price(\WC_Product $product):array{
        $s=self::settings();if(empty($s['finance_configured']))throw new \DomainException('finance_not_configured');
        if(self::mode()!=='standalone')throw new \DomainException('agah_reservation_api_required');
        $p=self::model($product);if(!$p)throw new \DomainException('not_gold');
        if(in_array($p['category']??'',['melted','wallet','exchange','presale','buyback','consignment'],true)&&empty($s['feature_flags'][$p['category']]))throw new \DomainException('feature_disabled');
        if(in_array($p['supply_status']??'available',['unavailable','request_quote','made_to_order'],true))throw new \DomainException('quote_required');
        $type=$p['price_type']??'INHERIT';if($type==='INHERIT')$type=$s['category_strategies'][$p['category']??'new']??$s['pricing_strategy']??'REQUEST_QUOTE';$p['price_type']=$type;
        $rate=in_array($type,['WEIGHT_BASED','MARKET_QUOTE'],true)?Rates::resolve($p['rate_source']??'manual',(array)($s['rate_fallbacks']??[])):[];
        return Pricing::calculate($p,$rate,$s['policy'],time());
    }
    public static function purchasable(bool $yes,\WC_Product $product):bool{
        if(!self::model($product))return $yes;
        try{self::price($product);return $product->exists()&&$product->get_status()==='publish'&&$product->is_in_stock();}catch(\Throwable $e){return false;}
    }
    public static function priceHtml(string $html,\WC_Product $product):string{
        if(!self::model($product))return $html;
        try{$p=self::price($product);return '<span class="gc-price">'.esc_html(Decimal::display($p['amount_irr'],self::settings()['display_currency']??'IRR')).' '.esc_html((self::settings()['display_currency']??'IRR')==='IRT'?'تومان':'ریال').'</span>';}catch(\Throwable $e){return '<span class="gc-price-request">درخواست قیمت معتبر</span>';}
    }
    public static function cartData(array $data,int $productId,int $variationId):array{
        $product=wc_get_product($variationId?:$productId);if($product&&self::model($product))$data['gc_snapshot']=self::price($product);return $data;
    }
    public static function cartPrices(\WC_Cart $cart):void{
        if(is_admin()&&!wp_doing_ajax())return;
        foreach($cart->get_cart() as $key=>$row){
            if(!self::model($row['data']))continue;
            try{$snapshot=self::price($row['data']);$old=$row['gc_snapshot']??null;
                if(!$old||self::fingerprint($old)!==self::fingerprint($snapshot))$cart->cart_contents[$key]['gc_changed']=true;
                $cart->cart_contents[$key]['gc_current']=$snapshot;$row['data']->set_price($snapshot['amount_irr']);$row['data']->set_tax_status('none');
                unset($cart->cart_contents[$key]['gc_error']);
            }catch(\Throwable $e){$cart->cart_contents[$key]['gc_error']=true;$row['data']->set_price('0');}
        }
    }
    public static function fingerprint(array $s):string{unset($s['created_at_utc']);return hash('sha256',wp_json_encode($s));}
    public static function checkCart():void{
        if(!WC()->cart)return;foreach(WC()->cart->get_cart() as $row){
            if(!empty($row['gc_error']))wc_add_notice('قیمت معتبر در دسترس نیست؛ برای دریافت مظنه تماس بگیرید.','error');
            elseif(!empty($row['gc_changed']))wc_add_notice('قیمت تغییر کرده است؛ در سبد خرید مبلغ جدید را تأیید کنید.','error');
            if(self::model($row['data'])&&$row['data']->needs_shipping()&&empty(self::settings()['shipping_configured']))wc_add_notice('روش تحویل فروشگاه هنوز پیکربندی نشده است.','error');
        }
    }
    public static function orderItem(\WC_Order_Item_Product $item,string $key,array $values,\WC_Order $order):void{
        if(isset($values['gc_snapshot'])){$item->add_meta_data('_gc_snapshot',wp_json_encode($values['gc_snapshot']),true);$order->update_meta_data('_gc_specialized','yes');}
    }
    public static function checkout(int $orderId,array $posted,\WC_Order $order):void{
        if($order->get_meta('_gc_specialized')!=='yes')return;
        $q=Quotes::issue($orderId,null,true);Quotes::begin($q['uuid'],$q['token']);
        $source=WC()->session?->get('gc_attribution',[]);$order->update_meta_data('_gc_attribution',$source);$order->save();
    }
    public static function paymentStatuses(array $statuses,\WC_Order $order):array{
        if($order->get_meta('_gc_specialized')!=='yes')return $statuses;
        $q=Quotes::latest($order->get_id());
        if(!$q||$q['status']!=='payment_started'||Quotes::deadline($q)<time()||!hash_equals((string)$q['intent'],(string)WC()->session?->get('gc_payment_'.$order->get_id(),'')))return [];
        return $statuses;
    }
    public static function guardPay(\WC_Order $order):void{if($order->get_meta('_gc_specialized')==='yes'&&!self::paymentStatuses(['pending'],$order))throw new \Exception('برای این سفارش لینک مظنه معتبر لازم است.');}
    public static function beforePaid(int $orderId,string $transaction=''):void{
        $order=wc_get_order($orderId);if(!$order||$order->get_meta('_gc_specialized')!=='yes'||$order->get_meta('_gc_settled')==='yes')return;
        $q=Quotes::latest($orderId);
        if(!$q||$q['status']!=='payment_started'||Decimal::money((string)$order->get_total())!==$q['amount_irr']||Quotes::deadline($q)<time()){
            if($q&&$transaction!=='')Quotes::settle($orderId,$transaction,Decimal::money((string)$order->get_total()),(string)$order->get_meta('_gc_intent'),sanitize_key($order->get_payment_method()?:'woo-gateway'));
            $order=wc_get_order($orderId);$order->set_transaction_id($transaction);
            $order->update_meta_data('_gc_settled','review');$order->update_status('on-hold','نتیجه درگاه نیازمند تطبیق است.');$order->save();throw new \Exception('payment_review_required');
        }
    }
    public static function genericPaid(int $orderId):void{
        $order=wc_get_order($orderId);if(!$order||$order->get_meta('_gc_specialized')!=='yes'||$order->get_meta('_gc_settled')==='yes')return;
        $q=Quotes::latest($orderId);if($q)Quotes::settle($orderId,(string)$order->get_transaction_id(),Decimal::money((string)$order->get_total()),(string)$order->get_meta('_gc_intent'),sanitize_key($order->get_payment_method()?:'woo-gateway'));
    }
    public static function isPrivate():bool{return is_cart()||is_checkout()||is_account_page()||is_search()||isset($_GET['gc_quote'])||is_page((int)(get_option('gc_pages',[])['tracking']??0));}
    public static function privateHeaders():void{if(self::isPrivate()){if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');}}
    public static function robots(array $r):array{if(self::isPrivate()||is_tag()||is_tax('product_tag')){$r['noindex']=true;unset($r['index']);}return $r;}
    public static function schema(array $data,\WC_Product $product):array{
        if(self::model($product)){
            try{
                $s=self::price($product);if($product->is_type('variable')){unset($data['offers']);return $data;}
                $offer=['@type'=>'Offer','url'=>$product->get_permalink(),'price'=>$s['amount_irr'],'priceCurrency'=>'IRR','availability'=>'https://schema.org/'.($product->is_in_stock()?'InStock':'OutOfStock')];
                if(!empty($s['rate']['valid_until']))$offer['priceSpecification']=['@type'=>'UnitPriceSpecification','price'=>$s['amount_irr'],'priceCurrency'=>'IRR','validThrough'=>gmdate('c',(int)$s['rate']['valid_until'])];
                $data['offers']=[$offer];
            }catch(\Throwable $e){unset($data['offers']);}
        }return $data;
    }
}
