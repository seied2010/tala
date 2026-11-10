<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Admin {
    public static function menu():void{
        add_menu_page('Gold Commerce','فروش طلا','gc_view_reports','gold-commerce',[self::class,'page'],'dashicons-store',56);
        add_submenu_page('gold-commerce','تنظیمات','تنظیمات','gc_manage_settings','gc-settings',[self::class,'settingsPage']);
        add_submenu_page('gold-commerce','نرخ','نرخ دستی','gc_manage_rates','gc-rates',[self::class,'ratePage']);
        add_submenu_page('gold-commerce','مظنه','استعلام و مظنه','gc_issue_quotes','gc-quotes',[self::class,'quotePage']);
        add_submenu_page('gold-commerce','ارسال و پشتیبانی','ارسال و پشتیبانی','gc_fulfil_orders','gc-fulfilment',[self::class,'fulfilmentPage']);
    }
    private static function start(string $action):void{echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="gc_action"><input type="hidden" name="gc_action" value="'.esc_attr($action).'">';wp_nonce_field('gc_'.$action);}
    private static function end():void{submit_button('ثبت');echo '</form>';}
    public static function health():array{
        global $wpdb;$states=[];foreach(['integration_outbox','notifications'] as $name)$states[$name]=$wpdb->get_results('SELECT state,COUNT(*) count FROM '.Database::table($name).' GROUP BY state',ARRAY_A);
        return ['version'=>GC_VERSION,'mode'=>Plugin::mode(),'agah'=>has_filter('gc_agah_status')?apply_filters('gc_agah_status','BLOCKED_EXTERNAL'):'DISABLED','php'=>PHP_VERSION,'wp'=>get_bloginfo('version'),'woo'=>WC_VERSION,'hpos'=>get_option('woocommerce_custom_orders_table_enabled'),'cron_next'=>wp_next_scheduled('gc_maintenance'),'cron_last'=>get_option('gc_last_maintenance',null),'queues'=>$states,'finance_configured'=>!empty(Plugin::settings()['finance_configured'])];
    }
    public static function page():void{
        if(!current_user_can('gc_view_reports'))wp_die('forbidden');echo '<div class="wrap" dir="rtl"><h1>مرکز فروش طلا</h1>';
        $health=self::health();echo '<p>وضعیت اتصال: <strong>'.esc_html($health['mode'].' / '.$health['agah']).'</strong></p>';
        echo '<h2>خروجی گزارش فروش قطعی</h2><form method="get" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="gc_export">';wp_nonce_field('gc_export');
        echo '<label>از تاریخ <input type="date" name="from" required></label> <label>تا تاریخ <input type="date" name="to" required></label> <select name="format"><option>xlsx</option><option>docx</option><option>pdf</option></select>';
        echo '<select name="type">';foreach(['sales'=>'فروش قطعی','daily'=>'روزانه','product'=>'محصول','category'=>'دسته','channel'=>'منبع جذب','inventory'=>'موجودی','quotes'=>'استعلام و تبدیل','payments'=>'پرداخت و مغایرت','sync'=>'صف و همگام‌سازی'] as $key=>$label)echo '<option value="'.$key.'">'.$label.'</option>';echo '</select>';
        submit_button('دریافت گزارش');echo '</form><h2>سلامت سیستم</h2><pre dir="ltr">'.esc_html(wp_json_encode($health,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></div>';
    }
    public static function settingsPage():void{
        if(!current_user_can('gc_manage_settings'))wp_die('forbidden');$s=Plugin::settings();echo '<div class="wrap" dir="rtl"><h1>پیکربندی مالی و فروشگاه</h1><p>قواعد مالی باید توسط مدیر فروشگاه تأیید شوند. نرخ و مالیات پیش‌فرض، تأیید قانونی یا صنفی محسوب نمی‌شوند.</p>';
        self::start('settings');
        foreach(['quote_ttl'=>'اعتبار مظنه (ثانیه)','payment_grace'=>'مهلت بازگشت درگاه (ثانیه)','rate_ttl'=>'اعتبار نرخ منبع (ثانیه)'] as $key=>$label)echo '<p><label>'.esc_html($label).' <input type="number" min="10" max="86400" name="'.$key.'" value="'.esc_attr((string)($s[$key]??60)).'" required></label></p>';
        echo '<p><label>نمایش قیمت <select name="display_currency">';foreach(['IRR'=>'ریال','IRT'=>'تومان'] as $key=>$label)echo '<option value="'.$key.'" '.selected($s['display_currency']??'IRR',$key,false).'>'.$label.'</option>';echo '</select></label></p>';
        echo '<p><label>نسخه قواعد مالی <input name="policy_version" value="'.esc_attr($s['policy']['version']??'').'"></label></p><p><label>درصد مالیات <input name="tax_percent" value="'.esc_attr($s['policy']['tax_percent']??'0').'"></label></p>';
        $types=['REQUEST_QUOTE'=>'استعلام','WEIGHT_BASED'=>'نرخ وزنی','MARKET_QUOTE'=>'نرخ مستقیم','FIXED'=>'ثابت با اجزا','MANUAL_FINAL'=>'مبلغ نهایی دستی'];
        echo '<p><label>روش پیش‌فرض فروشگاه <select name="pricing_strategy">';foreach($types as $key=>$label)echo '<option value="'.$key.'" '.selected($s['pricing_strategy']??'REQUEST_QUOTE',$key,false).'>'.$label.'</option>';echo '</select></label></p>';
        foreach(['new'=>'مصنوع نو','used'=>'دست دوم','stone'=>'سنگ‌دار','coin'=>'سکه','bullion'=>'شمش','custom'=>'سفارشی'] as $category=>$label){echo '<p><label>روش گروه '.$label.' <select name="category_strategy['.$category.']"><option value="INHERIT">ارث‌بری از فروشگاه</option>';foreach($types as $key=>$title)echo '<option value="'.$key.'" '.selected($s['category_strategies'][$category]??'INHERIT',$key,false).'>'.$title.'</option>';echo '</select></label></p>';}
        echo '<p><label>منابع جایگزین نرخ به ترتیب (نام‌های ثبت‌شده، با کاما؛ خالی یعنی توقف امن) <input name="rate_fallbacks" value="'.esc_attr(implode(',',(array)($s['rate_fallbacks']??[]))).'"></label></p>';
        echo '<p><label>پیشوند شماره فاکتور <input name="invoice_prefix" value="'.esc_attr($s['invoice_prefix']??'GC-').'"></label></p>';
        echo '<p><label>یادآوری پیش از انقضا (ثانیه) <input type="number" min="1" max="86400" name="quote_warn_seconds" value="'.esc_attr((string)($s['quote_warn_seconds']??60)).'"></label></p>';
        echo '<p><label>نگهداری محتوای اعلان تحویل‌شده/ناموفق (روز؛ صفر یعنی حذف خودکار غیرفعال) <input type="number" min="0" max="3650" name="log_retention_days" value="'.esc_attr((string)($s['log_retention_days']??0)).'"></label></p>';
        foreach(['metal'=>'اصل طلا','making'=>'اجرت','profit'=>'سود','stone'=>'سنگ','services'=>'خدمات'] as $k=>$label)echo '<label><input type="checkbox" name="tax_components[]" value="'.$k.'" '.checked(in_array($k,$s['policy']['tax_components']??[],true),true,false).'>'.$label.'</label> ';
        echo '<p><label>گردکردن <select name="rounding">';foreach(['half_up','down','up'] as $r)echo '<option '.selected($s['policy']['rounding']??'half_up',$r,false).'>'.$r.'</option>';echo '</select></label></p>';
        foreach(['finance_configured'=>'قواعد مالی توسط من بررسی و تأیید شد','shipping_configured'=>'روش‌های ارسال ووکامرس پیکربندی و آزموده شده‌اند','guest_quotes'=>'استعلام مهمان فعال باشد','test_mode'=>'درگاه آزمایشی در محیط غیرتولیدی فعال باشد'] as $key=>$label)echo '<p><label><input type="checkbox" name="'.$key.'" '.checked(!empty($s[$key]),true,false).'>'.esc_html($label).'</label></p>';
        echo '<p><label>اطلاعات تماس فروشگاه <textarea name="contact_text">'.esc_textarea(get_option('gc_contact_text','')).'</textarea></label></p>';self::end();
        echo '<h2>راه‌اندازی تسویه کلاسیک</h2><p>این نسخه مسیر کلاسیک را پشتیبانی می‌کند. پس از بررسی صفحات موجود، دکمه زیر محتوای صفحات سبد و تسویه را با shortcode استاندارد جایگزین می‌کند و نسخه قبلی را برای بازیابی نگه می‌دارد.</p>';self::start('classic');echo '<label><input type="checkbox" name="approved" value="1" required>صفحات را بررسی کردم و تغییر آن‌ها را تأیید می‌کنم.</label>';self::end();echo '</div>';
    }
    public static function ratePage():void{
        if(!current_user_can('gc_manage_rates'))wp_die('forbidden');echo '<div class="wrap" dir="rtl"><h1>ثبت نرخ نسخه‌دار</h1>';self::start('rate');
        echo '<p><label>نرخ (ریال) <input name="value" inputmode="decimal" required></label></p><p><select name="unit"><option value="IRR_PER_GRAM">ریال به گرم</option><option value="IRR_PER_ITEM">ریال به عدد</option></select></p><p><label>عیار مرجع (در هزار) <input name="reference_purity" value="750" required></label></p>';self::end();echo '</div>';
        echo '<div class="wrap" dir="rtl"><h2>منبع نرخ خودکار (قرارداد API_RATE)</h2><p>کلید را در محیط سرور با نام GC_RATE_... وارد کنید؛ در این فرم فقط نام متغیر را بنویسید.</p>';self::start('source');echo '<p><label>شناسه منبع <input name="source_name" required></label></p><p><label>URL امن HTTPS <input type="url" name="source_url" required></label></p><p><label>نام متغیر امن <input name="credential_env" placeholder="GC_RATE_PROVIDER"></label></p>';self::end();global $wpdb;foreach($wpdb->get_results('SELECT name,health,last_success FROM '.Database::table('rate_sources'),ARRAY_A) as $r)echo '<p>'.esc_html($r['name'].' / '.$r['health'].' / '.($r['last_success']?gmdate('c',(int)$r['last_success']):'بدون دریافت موفق')).'</p>';echo '</div>';
    }
    public static function quotePage():void{
        if(!current_user_can('gc_issue_quotes'))wp_die('forbidden');$orders=wc_get_orders(['limit'=>50,'status'=>['gc-await-quote','pending'],'meta_query'=>[['key'=>'_gc_specialized','value'=>'yes']],'orderby'=>'date','order'=>'DESC']);
        echo '<div class="wrap" dir="rtl"><h1>استعلام‌ها و مظنه‌ها</h1><p>برای کالای فیزیکی ابتدا روش و هزینه ارسال را در سفارش WooCommerce مشخص کنید. مبلغ دستی، مبلغ کل ردیف واحد است؛ حمل از سفارش به آن افزوده می‌شود.</p>';
        foreach($orders as $o){echo '<section><h2><a href="'.esc_url($o->get_edit_order_url()).'">سفارش '.esc_html((string)$o->get_id()).'</a></h2>';self::start('issue');echo '<input type="hidden" name="order_id" value="'.esc_attr((string)$o->get_id()).'"><label>قیمت تأییدشدهٔ دستی ردیف (ریال؛ اختیاری) <input name="approved_amount" inputmode="numeric"></label>';self::end();echo '</section>';}
        echo '</div>';
    }
    public static function fulfilmentPage():void{
        if(!current_user_can('gc_fulfil_orders'))wp_die('forbidden');echo '<div class="wrap" dir="rtl"><h1>ارسال و خدمات مشتری</h1>';self::start('shipment');
        echo '<p><label>شماره سفارش <input type="number" min="1" name="order_id" required></label></p><p><label>کد رهگیری <input name="tracking" required></label></p><select name="status">';foreach(['gc-preparing'=>'آماده‌سازی','gc-ready'=>'آماده ارسال','gc-shipped'=>'ارسال شده','gc-delivered'=>'تحویل شده'] as $v=>$label)echo '<option value="'.$v.'">'.$label.'</option>';echo '</select>';self::end();
        global $wpdb;echo '<h2>درخواست‌های مشتری</h2>';foreach($wpdb->get_results('SELECT * FROM '.Database::table('support').' ORDER BY id DESC LIMIT 50',ARRAY_A) as $r){echo '<article><p>'.esc_html('#'.$r['id'].' / '.$r['kind'].' / '.$r['status'].' / سفارش '.$r['order_id']).'</p><p>'.esc_html($r['message']).'</p>';self::start('support');echo '<input type="hidden" name="id" value="'.esc_attr($r['id']).'"><select name="status"><option>open</option><option>reviewing</option><option>resolved</option><option>rejected</option></select>';self::end();echo '</article>';}echo '</div>';
    }
    public static function action():void{
        $action=sanitize_key($_POST['gc_action']??'');$caps=['settings'=>'gc_manage_settings','classic'=>'gc_manage_settings','rate'=>'gc_manage_rates','source'=>'gc_manage_rates','issue'=>'gc_issue_quotes','shipment'=>'gc_fulfil_orders','support'=>'gc_support'];
        if(!isset($caps[$action])||!current_user_can($caps[$action]))wp_die('forbidden',403);check_admin_referer('gc_'.$action);
        try{
            if($action==='settings'){
                $s=Plugin::settings();foreach(['quote_ttl','payment_grace','rate_ttl'] as $k){$n=filter_var($_POST[$k]??null,FILTER_VALIDATE_INT);if($n===false||$n<10||$n>86400)throw new \DomainException('ttl_invalid');$s[$k]=$n;}
                $pct=(string)wp_unslash($_POST['tax_percent']??'');if(Decimal::cmp(Decimal::parse($pct),'1000000')>0)throw new \DomainException('tax_invalid');
                $bases=array_values(array_intersect((array)($_POST['tax_components']??[]),['metal','making','profit','stone','services']));$round=sanitize_key($_POST['rounding']??'');if(!in_array($round,['half_up','down','up'],true))throw new \DomainException('rounding_invalid');
                $version=sanitize_text_field($_POST['policy_version']??'');if(!$version)throw new \DomainException('policy_version_required');
                $s['policy']=['version'=>$version,'tax_percent'=>$pct,'tax_components'=>$bases,'rounding'=>$round];
                $types=['REQUEST_QUOTE','WEIGHT_BASED','MARKET_QUOTE','FIXED','MANUAL_FINAL'];$strategy=(string)($_POST['pricing_strategy']??'REQUEST_QUOTE');if(!in_array($strategy,$types,true))throw new \DomainException('strategy_invalid');$s['pricing_strategy']=$strategy;$s['category_strategies']=[];
                foreach((array)($_POST['category_strategy']??[]) as $cat=>$v)if(in_array($cat,['new','used','stone','coin','bullion','custom'],true)&&in_array($v,$types,true))$s['category_strategies'][$cat]=$v;
                $s['rate_fallbacks']=array_values(array_filter(array_map('sanitize_key',explode(',',(string)($_POST['rate_fallbacks']??'')))));
                $s['invoice_prefix']=substr(sanitize_text_field(wp_unslash($_POST['invoice_prefix']??'GC-')),0,32);
                $s['quote_warn_seconds']=min(86400,max(1,(int)($_POST['quote_warn_seconds']??60)));
                $s['log_retention_days']=min(3650,max(0,(int)($_POST['log_retention_days']??0)));
                foreach(['finance_configured','shipping_configured','guest_quotes','test_mode'] as $k)$s[$k]=isset($_POST[$k]);
                $s['display_currency']=in_array($_POST['display_currency']??'',['IRR','IRT'],true)?$_POST['display_currency']:'IRR';
                if($s['finance_configured']&&get_woocommerce_currency()!=='IRR')throw new \DomainException('woocommerce_currency_must_be_IRR');
                update_option('gc_settings',$s);update_option('gc_contact_text',sanitize_textarea_field(wp_unslash($_POST['contact_text']??'')));Database::audit('settings.updated','store',['version'=>$version]);
            }elseif($action==='rate')Rates::put(['value'=>(string)wp_unslash($_POST['value']??''),'unit'=>sanitize_text_field($_POST['unit']??''),'reference_purity'=>(string)wp_unslash($_POST['reference_purity']??''),'currency'=>'IRR','valid_until'=>time()+(int)Plugin::settings()['rate_ttl']]);
            elseif($action==='source')Rates::addSource(sanitize_key($_POST['source_name']??''),esc_url_raw(wp_unslash($_POST['source_url']??'')),sanitize_text_field($_POST['credential_env']??''));
            elseif($action==='classic')ClassicCheckout::configure(!empty($_POST['approved']));
            elseif($action==='issue'){Quotes::issue((int)($_POST['order_id']??0),($_POST['approved_amount']??'')!==''?(string)wp_unslash($_POST['approved_amount']):null);}
            elseif($action==='shipment'){
                $o=wc_get_order((int)($_POST['order_id']??0));if(!$o||!$o->is_paid())throw new \DomainException('paid_order_required');$status=sanitize_key($_POST['status']??'');if(!in_array($status,['gc-preparing','gc-ready','gc-shipped','gc-delivered'],true))throw new \DomainException('status_invalid');
                $tracking=sanitize_text_field(wp_unslash($_POST['tracking']??''));$o->update_meta_data('_gc_tracking',$tracking);$o->update_meta_data('_gc_fulfilment_status',$status);$o->save();Database::audit('shipment.updated',(string)$o->get_id(),['status'=>$status]);Notifications::enqueue('shipment:'.$o->get_id().':'.hash('sha256',$tracking.$status),$o->get_billing_email(),'وضعیت ارسال سفارش',$status.' — '.$tracking);
            }else{global $wpdb;$status=sanitize_key($_POST['status']??'');if(!in_array($status,['open','reviewing','resolved','rejected'],true))throw new \DomainException('status_invalid');$wpdb->update(Database::table('support'),['status'=>$status],['id'=>(int)($_POST['id']??0)]);Database::audit('support.updated',(string)(int)($_POST['id']??0),['status'=>$status]);}
            wp_safe_redirect(admin_url('admin.php?page=gold-commerce&gc_saved=1'));exit;
        }catch(\Throwable $e){wp_die(esc_html('ثبت انجام نشد: '.$e->getMessage()),'Gold Commerce',['response'=>409]);}
    }
    public static function productFields():void{
        global $product_object;$p=$product_object?self::modelForEditor($product_object):[];
        echo '<div class="options_group"><h3 style="padding:0 12px">اطلاعات تخصصی طلا</h3>';
        woocommerce_wp_checkbox(['id'=>'gc_enabled','label'=>'محصول طلا','value'=>$p?'yes':'no']);
        woocommerce_wp_select(['id'=>'gc_category','label'=>'گروه کالا','value'=>$p['category']??'new','options'=>['new'=>'مصنوع نو','used'=>'دست دوم','stone'=>'سنگ‌دار','coin'=>'سکه','bullion'=>'شمش','custom'=>'سفارشی','melted'=>'آب‌شده (غیرفعال پیش‌فرض)']]);
        woocommerce_wp_select(['id'=>'gc_price_type','label'=>'قیمت‌گذاری','value'=>$p['price_type']??'REQUEST_QUOTE','options'=>['INHERIT'=>'ارث‌بری از گروه/فروشگاه','REQUEST_QUOTE'=>'استعلام','WEIGHT_BASED'=>'وزنی با نرخ','MARKET_QUOTE'=>'نرخ مستقیم سکه/شمش','FIXED'=>'ثابت با اجزا','MANUAL_FINAL'=>'مبلغ نهایی دستی']]);
        foreach(['gross_weight'=>'وزن کل (گرم)','net_weight'=>'وزن خالص طلا','stone_weight'=>'وزن سنگ (گرم)','purity'=>'عیار (در هزار)','stone_value'=>'ارزش سنگ (ریال)','fixed_price'=>'قیمت ثابت (ریال)','making_value'=>'اجرت','profit_value'=>'سود','services'=>'خدمات (ریال)','rate_source'=>'منبع نرخ'] as $k=>$label)woocommerce_wp_text_input(['id'=>'gc_'.$k,'label'=>$label,'value'=>$p[$k]??($k==='purity'?'750':($k==='rate_source'?'manual':'0'))]);
        foreach(['making'=>'اجرت','profit'=>'سود'] as $k=>$label)woocommerce_wp_select(['id'=>'gc_'.$k.'_mode','label'=>'نوع '.$label,'value'=>$p[$k.'_mode']??'fixed','options'=>['fixed'=>'ثابت','per_gram'=>'به گرم','percent'=>'درصد']]);
        foreach(['metal'=>'اصل طلا','making'=>'اجرت','profit'=>'سود','stone'=>'سنگ','services'=>'خدمات'] as $k=>$label)woocommerce_wp_text_input(['id'=>'gc_discount_'.$k,'label'=>'تخفیف '.$label.' (ریال)','value'=>$p['discounts'][$k]??'0']);
        foreach(['making'=>'پایه اجرت درصدی','profit'=>'پایه سود درصدی'] as $key=>$label)woocommerce_wp_text_input(['id'=>'gc_'.$key.'_base','label'=>$label.' (کلیدهای اجزا با کاما)','value'=>implode(',',(array)($p[$key.'_base']??($key==='making'?['metal']:['metal','making'])))]);
        woocommerce_wp_select(['id'=>'gc_supply_status','label'=>'وضعیت تأمین','value'=>$p['supply_status']??'available','options'=>['available'=>'موجود','unavailable'=>'ناموجود','made_to_order'=>'سفارش ساخت','request_quote'=>'استعلام','sourcable'=>'قابل تأمین']]);
        echo '</div>';
    }
    private static function modelForEditor(\WC_Product $p):array{return (array)$p->get_meta('_gc_product');}
    public static function saveProduct(\WC_Product $product):void{
        if(!current_user_can('edit_post',$product->get_id()))return;
        if(empty($_POST['gc_enabled'])){$product->delete_meta_data('_gc_product');return;}
        $p=[];
        foreach(['category','price_type','gross_weight','net_weight','stone_weight','purity','stone_value','fixed_price','making_value','profit_value','services','rate_source','making_mode','profit_mode','supply_status'] as $key)$p[$key]=sanitize_text_field(wp_unslash($_POST['gc_'.$key]??''));
        try{
            foreach(['gross_weight','net_weight','stone_weight','purity','stone_value','fixed_price','making_value','profit_value','services'] as $key)Decimal::parse($p[$key]);
            $p['discounts']=[];foreach(['metal','making','profit','stone','services'] as $key){$value=(string)wp_unslash($_POST['gc_discount_'.$key]??'0');Decimal::parse($value);$p['discounts'][$key]=$value;}
            foreach(['making','profit'] as $key){$bases=array_values(array_filter(array_map('sanitize_key',explode(',',(string)wp_unslash($_POST['gc_'.$key.'_base']??($key==='making'?'metal':'metal,making'))))));if(array_diff($bases,['metal','making','profit','stone','services']))throw new \DomainException('fee_base_invalid');$p[$key.'_base']=$bases;}
            if(!in_array($p['price_type'],['INHERIT','REQUEST_QUOTE','WEIGHT_BASED','MARKET_QUOTE','FIXED','MANUAL_FINAL'],true))throw new \DomainException('price_type_invalid');
            $product->update_meta_data('_gc_product',$p);$product->set_tax_status('none');if(!$product->get_price())$product->set_regular_price('0');Database::audit('product.pricing_updated',(string)$product->get_id(),['model'=>$p]);
        }catch(\Throwable $e){\WC_Admin_Meta_Boxes::add_error('اطلاعات طلا معتبر نیست: '.$e->getMessage());}
    }
}
