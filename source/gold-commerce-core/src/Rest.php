<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Rest {
    public static function register():void{
        $ns='gold-commerce/v1';
        register_rest_route($ns,'/rates',['methods'=>'GET','permission_callback'=>[self::class,'publicAccess'],'callback'=>static function($r){return self::run(static function()use($r){return Rates::latest(sanitize_key($r['source']??'manual'));});}]);
        register_rest_route($ns,'/preview/(?P<id>\d+)',['methods'=>'GET','permission_callback'=>[self::class,'publicAccess'],'callback'=>static fn($r)=>self::run(static fn()=>Plugin::price(wc_get_product((int)$r['id']))) ]);
        register_rest_route($ns,'/quotes',['methods'=>'POST','args'=>['product_id'=>['type'=>'integer','minimum'=>1,'required'=>true],'quantity'=>['type'=>'integer','minimum'=>1,'maximum'=>100,'default'=>1],'email'=>['type'=>'string','format'=>'email','maxLength'=>254,'required'=>true],'name'=>['type'=>'string','maxLength'=>120]],'permission_callback'=>[self::class,'requestAccess'],'callback'=>static fn($r)=>self::run(static fn()=>Quotes::request((int)$r['product_id'],(int)($r['quantity']??1),['email'=>$r['email'],'name'=>$r['name']??'']))]);
        register_rest_route($ns,'/quotes/(?P<id>[a-f0-9-]{36})',['methods'=>'GET','permission_callback'=>[self::class,'ownerAccess'],'callback'=>static fn($r)=>self::run(static function()use($r){$q=Quotes::get((string)$r['id']);return array_intersect_key($q,array_flip(['uuid','order_id','status','version','expires_at','amount_irr']));})]);
        register_rest_route($ns,'/quotes/(?P<id>[a-f0-9-]{36})/payment',['methods'=>'POST','args'=>['token'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$','required'=>true]],'permission_callback'=>[self::class,'requestAccess'],'callback'=>static fn($r)=>self::run(static fn()=>Quotes::begin((string)$r['id'],(string)$r['token']))]);
        register_rest_route($ns,'/orders/(?P<id>\d+)/issue',['methods'=>'POST','permission_callback'=>static fn()=>current_user_can('gc_issue_quotes'),'callback'=>static fn($r)=>self::run(static fn()=>Quotes::issue((int)$r['id'],isset($r['approved_amount'])?(string)$r['approved_amount']:null))]);
        register_rest_route($ns,'/rates/manual',['methods'=>'POST','permission_callback'=>static fn()=>current_user_can('gc_manage_rates'),'callback'=>static fn($r)=>self::run(static fn()=>Rates::put((array)$r->get_json_params()))]);
        register_rest_route($ns,'/health',['methods'=>'GET','permission_callback'=>static fn()=>current_user_can('gc_manage_settings'),'callback'=>static fn()=>Admin::health()]);
        register_rest_route($ns,'/support',['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>static fn($r)=>self::run(static fn()=>Customer::support((int)$r['order_id'],(string)$r['kind'],(string)$r['message']))]);
    }
    public static function publicAccess():bool|\WP_Error{
        global $wpdb;$identity=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'local'),wp_salt('nonce'));$key='gc_limit_'.$identity.'_'.intdiv(time(),60);
        return Database::lock($key,static function()use($key){$count=(int)get_transient($key);if($count>=30)return new \WP_Error('rate_limited','لطفاً کمی بعد تلاش کنید.',['status'=>429]);set_transient($key,$count+1,120);return true;});
    }
    public static function requestAccess(\WP_REST_Request $r):bool|\WP_Error{
        $limit=self::publicAccess();if(is_wp_error($limit))return $limit;
        if(is_user_logged_in()&&!wp_verify_nonce($r->get_header('X-WP-Nonce'),'wp_rest'))return new \WP_Error('csrf','درخواست نامعتبر',['status'=>403]);
        $origin=$r->get_header('origin');if($origin&&wp_parse_url($origin,PHP_URL_HOST)!==wp_parse_url(home_url(),PHP_URL_HOST))return new \WP_Error('origin_denied','درخواست نامعتبر',['status'=>403]);
        return true;
    }
    public static function ownerAccess(\WP_REST_Request $r):bool|\WP_Error{
        $q=Quotes::get((string)$r['id']);$order=$q?wc_get_order($q['order_id']):null;
        if(!$order)return new \WP_Error('not_found','یافت نشد',['status'=>404]);
        if(current_user_can('gc_issue_quotes')||(get_current_user_id()&&$order->get_customer_id()===get_current_user_id()))return true;
        $token=(string)$r->get_header('X-GC-Owner');return strlen($token)===64&&hash_equals((string)$order->get_meta('_gc_owner_hash'),hash('sha256',$token));
    }
    public static function run(callable $fn):mixed{try{return $fn();}catch(\Throwable $e){$code=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'operation_failed';return new \WP_Error($code,'عملیات انجام نشد: '.$code,['status'=>409]);}}
}
