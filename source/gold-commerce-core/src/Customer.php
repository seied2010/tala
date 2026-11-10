<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Customer {
    public static function productRequest():void{
        global $product;if(!$product||!Plugin::model($product))return;
        try{$snapshot=Plugin::price($product);echo '<details class="gc-breakdown"><summary>جزئیات قیمت</summary><dl>';if(($snapshot['tax_treatment']??'')==='INCLUDED_UNITEMIZED'){echo '<dt>قیمت نهایی اعلام‌شده</dt><dd>'.esc_html($snapshot['amount_irr']).' ریال</dd></dl><p>تفکیک مالیات و اجزا در این مبلغ توسط فروشنده ارائه نشده است. برای قیمت تفصیلی استعلام بگیرید.</p>';}else{foreach($snapshot['components']+['tax'=>$snapshot['tax']] as $key=>$amount)echo '<dt>'.esc_html(['metal'=>'اصل طلا','making'=>'اجرت','profit'=>'سود','stone'=>'سنگ','services'=>'خدمات','tax'=>'مالیات','final_price'=>'قیمت نهایی'][$key]).'</dt><dd>'.esc_html($amount).' ریال</dd>';echo '</dl>';}echo '</details>';}catch(\Throwable $e){}
        echo '<details class="gc-request"><summary>درخواست استعلام قیمت</summary><form method="post"><input type="hidden" name="gc_request" value="'.esc_attr((string)$product->get_id()).'">';wp_nonce_field('gc_customer');
        echo '<label>نام <input name="gc_name" autocomplete="name"></label><label>ایمیل <input name="gc_email" type="email" autocomplete="email" required></label><label>تعداد <input name="gc_quantity" type="number" min="1" max="100" value="1" required></label><button type="submit">ثبت درخواست</button></form></details>';
        if(is_user_logged_in()){
            echo '<form method="post"><input type="hidden" name="gc_favourite" value="'.esc_attr((string)$product->get_id()).'">';wp_nonce_field('gc_customer');echo '<button type="submit">افزودن / حذف علاقه‌مندی</button></form>';
        }
    }
    public static function route():void{
        if(WC()->session&&!WC()->session->get('gc_attribution')){
            $source=[];foreach(['utm_source','utm_medium','utm_campaign','referrer'] as $key)if(isset($_GET[$key]))$source[$key]=substr(sanitize_text_field(wp_unslash($_GET[$key])),0,120);
            if($source)WC()->session->set('gc_attribution',$source);
        }
        if(isset($_GET['gc_quote'])){
            Plugin::privateHeaders();
            // GET only displays confirmation. Email scanners cannot consume a one-time token.
            $uuid=sanitize_text_field(wp_unslash($_GET['gc_quote']));$token=sanitize_text_field(wp_unslash($_GET['gc_token']??''));
            $q=Quotes::get($uuid);
            if(!$q||strlen($token)!==64||!hash_equals($q['token_hash'],hash('sha256',$token))||$q['status']!=='issued'||(int)$q['expires_at']<=time())wp_die('مظنه معتبر نیست یا منقضی شده است. لطفاً قیمت جدید درخواست کنید.','مظنه',['response'=>410]);
            if($_SERVER['REQUEST_METHOD']==='POST'){
                check_admin_referer('gc_begin_'.$uuid);
                try{$result=Quotes::begin($uuid,$token);wp_safe_redirect($result['payment_url']);exit;}catch(\Throwable $e){wp_die('امکان شروع پرداخت نیست: '.esc_html($e->getMessage()),'مظنه',['response'=>409]);}
            }
            get_header();echo '<main id="main-content" class="gc-payment"><h1>تأیید مظنه</h1><p>سفارش '.esc_html((string)$q['order_id']).' — مبلغ '.esc_html($q['amount_irr']).' ریال</p><p>اعتبار تا '.esc_html(wp_date('Y/m/d H:i:s',(int)$q['expires_at'])).'</p><form method="post">';wp_nonce_field('gc_begin_'.$uuid);echo '<button type="submit">تأیید و رفتن به پرداخت امن</button></form></main>';get_footer();exit;
        }
        if($_SERVER['REQUEST_METHOD']!=='POST')return;
        if(isset($_POST['gc_request'])){
            check_admin_referer('gc_customer');try{
                $r=Quotes::request((int)$_POST['gc_request'],(int)($_POST['gc_quantity']??1),['email'=>sanitize_email(wp_unslash($_POST['gc_email']??'')),'name'=>sanitize_text_field(wp_unslash($_POST['gc_name']??''))]);
                if(WC()->session){$tokens=(array)WC()->session->get('gc_owner_tokens',[]);$tokens[$r['order_id']]=$r['owner_token'];WC()->session->set('gc_owner_tokens',$tokens);WC()->session->set_customer_session_cookie(true);}
                wc_add_notice('درخواست شما ثبت شد. شماره پیگیری: '.$r['order_id'],'success');
            }catch(\Throwable $e){wc_add_notice('ثبت درخواست انجام نشد: '.$e->getMessage(),'error');}
            wp_safe_redirect(wp_get_referer()?:wc_get_page_permalink('shop'));exit;
        }
        if(isset($_POST['gc_accept_price'])){
            check_admin_referer('gc_customer');foreach(WC()->cart->cart_contents as &$row)if(isset($row['gc_current'])){$row['gc_snapshot']=$row['gc_current'];unset($row['gc_changed']);}unset($row);WC()->cart->set_session();wp_safe_redirect(wc_get_cart_url());exit;
        }
        if(isset($_POST['gc_favourite'])){
            check_admin_referer('gc_customer');if(!get_current_user_id())wp_die('ورود لازم است.');$id=(int)$_POST['gc_favourite'];if(!wc_get_product($id))wp_die('محصول نامعتبر');
            $ids=array_map('intval',(array)get_user_meta(get_current_user_id(),'gc_favourites',true));$ids=in_array($id,$ids,true)?array_values(array_diff($ids,[$id])):array_slice(array_merge($ids,[$id]),-100);update_user_meta(get_current_user_id(),'gc_favourites',$ids);wp_safe_redirect(get_permalink($id));exit;
        }
        if(isset($_POST['gc_support_order'])){
            check_admin_referer('gc_customer');try{self::support((int)$_POST['gc_support_order'],sanitize_key($_POST['gc_kind']??''),sanitize_textarea_field(wp_unslash($_POST['gc_message']??'')));wc_add_notice('درخواست ثبت شد.','success');}catch(\Throwable $e){wc_add_notice('ثبت درخواست ممکن نیست.','error');}wp_safe_redirect(wc_get_page_permalink('myaccount'));exit;
        }
        if(isset($_POST['gc_invoice'])){
            check_admin_referer('gc_customer');$o=wc_get_order((int)$_POST['gc_invoice']);if(!$o||!self::owns($o))wp_die('forbidden',403);Reports::invoice($o);exit;
        }
    }
    public static function acceptPriceForm():void{echo '<form method="post"><input type="hidden" name="gc_accept_price" value="1">';wp_nonce_field('gc_customer');echo '<button type="submit">قیمت‌های فعلی سبد را بررسی و تأیید کردم</button></form>';}
    public static function owns(\WC_Order $o):bool{
        if(current_user_can('manage_woocommerce')||current_user_can('gc_issue_quotes')||(get_current_user_id()&&$o->get_customer_id()===get_current_user_id()))return true;
        $token=(WC()->session?->get('gc_owner_tokens',[])??[])[$o->get_id()]??'';return strlen($token)===64&&hash_equals((string)$o->get_meta('_gc_owner_hash'),hash('sha256',$token));
    }
    public static function tracking():string{
        ob_start();echo '<h2>پیگیری امن سفارش</h2><p>برای دیدن سفارش وارد حساب شوید یا از همان مرورگر ثبت استعلام استفاده کنید.</p>';
        echo '<form method="post"><label>شماره سفارش <input name="gc_tracking_order" type="number" min="1" required></label>';wp_nonce_field('gc_tracking');echo '<button>پیگیری</button></form>';
        if(isset($_POST['gc_tracking_order'])){
            if(!wp_verify_nonce($_POST['_wpnonce']??'','gc_tracking'))return 'درخواست نامعتبر';
            $order=wc_get_order((int)$_POST['gc_tracking_order']);if($order&&self::owns($order))echo '<p>'.esc_html(wc_get_order_status_name($order->get_status()).' / '.$order->get_meta('_gc_tracking')).'</p>';else echo '<p>سفارش در دسترس نیست.</p>';
        }return ob_get_clean();
    }
    public static function support(int $orderId,string $kind,string $message):array{
        global $wpdb;$o=wc_get_order($orderId);if(!$o||!get_current_user_id()||$o->get_customer_id()!==get_current_user_id())throw new \DomainException('forbidden');
        if(!in_array($kind,['question','cancel','return','dispute'],true)||strlen($message)<3||strlen($message)>4000)throw new \DomainException('support_invalid');
        $wpdb->insert(Database::table('support'),['order_id'=>$orderId,'user_id'=>get_current_user_id(),'kind'=>$kind,'status'=>'open','message'=>sanitize_textarea_field($message),'created_at'=>time()]);Database::audit('support.created',(string)$wpdb->insert_id,['kind'=>$kind]);return ['id'=>$wpdb->insert_id,'status'=>'open'];
    }
    public static function dashboard():void{
        echo '<h2>استعلام‌ها و علاقه‌مندی‌ها</h2>';
        foreach(wc_get_orders(['customer_id'=>get_current_user_id(),'status'=>['gc-await-quote','pending'],'limit'=>20]) as $o)echo '<p>استعلام '.esc_html((string)$o->get_id()).' — '.esc_html(wc_get_order_status_name($o->get_status())).'</p>';
        foreach((array)get_user_meta(get_current_user_id(),'gc_favourites',true) as $id)if($p=wc_get_product((int)$id))echo '<p><a href="'.esc_url($p->get_permalink()).'">'.esc_html($p->get_name()).'</a></p>';
        echo '<h2>درخواست پشتیبانی، لغو یا مرجوعی</h2><form method="post"><label>شماره سفارش <input type="number" name="gc_support_order" min="1" required></label><label>نوع درخواست <select name="gc_kind"><option value="question">پرسش</option><option value="cancel">لغو</option><option value="return">مرجوعی</option><option value="dispute">اختلاف</option></select></label><label>شرح <textarea name="gc_message" minlength="3" maxlength="4000" required></textarea></label>';wp_nonce_field('gc_customer');echo '<button>ثبت درخواست</button></form>';
    }
    public static function orderDetails(\WC_Order $o):void{
        if(!self::owns($o))return;echo '<p>وضعیت ارسال: '.esc_html((string)$o->get_meta('_gc_fulfilment_status')).' — رهگیری: '.esc_html((string)$o->get_meta('_gc_tracking')).'</p>';
        if($o->get_meta('_gc_invoice_snapshot')){echo '<form method="post"><input type="hidden" name="gc_invoice" value="'.esc_attr((string)$o->get_id()).'">';wp_nonce_field('gc_customer');echo '<button>دریافت فاکتور PDF</button></form>';}
    }
}
